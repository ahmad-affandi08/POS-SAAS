<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\StatusMutasiBank;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Layanan\PenguraiMutasiBank;
use App\Domain\Akuntansi\Model\ImporMutasiBank as ModelImpor;
use App\Domain\Akuntansi\Model\MutasiBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Impor\Layanan\PembacaBerkasTabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Impor rekening koran (FIN-09) untuk satu akun kas/bank aktif dari CSV atau Excel hasil unduhan internet banking.
 *
 * - Baris judul = baris pertama (dari 20 baris awal) yang memuat kolom tanggal dan nominal; baris di atasnya (nama
 *   rekening, periode) dilewati. Kolom dikenali dari sinonim: Tanggal, Keterangan/Uraian/Deskripsi, Kredit/Masuk
 *   (uang masuk), Debit/Keluar (uang keluar), Jumlah/Nominal (bertanda atau berakhiran CR/DB), Saldo.
 * - Baris tanpa tanggal yang sah (saldo awal, total, catatan kaki) dan baris bernominal nol dilewati.
 * - Duplikat (baris yang sudah pernah diimpor, misal unduhan bertumpuk) dilewati lewat `SidikBaris` unik per akun.
 * Maksimal 5.000 baris. Audit `mutasi-bank.impor` (jumlah saja).
 */
final class ImporMutasiBank
{
    public const MAKSIMAL_BARIS = 5000;

    /** @var array<string, list<string>> */
    private const SINONIM = [
        'Tanggal' => ['tanggal', 'tgl', 'date', 'tanggaltransaksi', 'tgltransaksi', 'transactiondate', 'tanggalefektif'],
        'Keterangan' => ['keterangan', 'uraian', 'deskripsi', 'description', 'berita', 'remark', 'remarks', 'keterangantransaksi', 'uraiantransaksi'],
        'Masuk' => ['kredit', 'credit', 'masuk', 'setoran', 'mutasikredit', 'cr'],
        'Keluar' => ['debit', 'debet', 'keluar', 'penarikan', 'mutasidebit', 'db'],
        'Jumlah' => ['jumlah', 'nominal', 'mutasi', 'amount', 'nilai'],
        'Saldo' => ['saldo', 'balance', 'saldoakhir'],
    ];

    public function __construct(
        private readonly PembacaBerkasTabel $pembaca,
        private readonly DaftarAkunPilihan $akun,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return array{Impor: ModelImpor, Bermasalah: list<array{Baris: int, Pesan: string}>}
     */
    public function Jalankan(string $path, string $namaBerkas, string $uuidAkun, int $idPengguna): array
    {
        $idAkun = $this->akun->CariKasBankDariUuid($uuidAkun)['Id'] ?? null;

        if ($idAkun === null) {
            throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas atau bank.', 'Akun');
        }

        $format = $this->pembaca->TentukanFormat($path, $namaBerkas);
        $sementara = null;
        $pemisah = null;

        if ($format === PembacaBerkasTabel::FORMAT_CSV) {
            $isi = $this->pembaca->NormalisasiCsv((string) file_get_contents($path));
            $pemisah = $this->pembaca->DeteksiPemisah($isi);
            $sementara = 'impor-mutasi-bank/'.Str::ulid().'.csv';
            Storage::disk('local')->put($sementara, $isi);
            $path = Storage::disk('local')->path($sementara);
        }

        try {
            [$baris, $bermasalah, $jumlah] = $this->BacaBaris($path, $format, $pemisah);
        } finally {
            if ($sementara !== null) {
                Storage::disk('local')->delete($sementara);
            }
        }

        return DB::transaction(function () use ($baris, $bermasalah, $jumlah, $idAkun, $namaBerkas, $idPengguna): array {
            $impor = ModelImpor::query()->create([
                'IdAkun' => $idAkun,
                'NamaBerkas' => mb_substr($namaBerkas, 0, 150),
                'JumlahBaris' => $jumlah,
                'DibuatOleh' => $idPengguna,
            ]);
            $sudah = [];

            foreach (array_chunk(array_keys($baris), 1000) as $potongan) {
                foreach (MutasiBank::query()->where('IdAkun', $idAkun)->whereIn('SidikBaris', $potongan)->pluck('SidikBaris') as $sidik) {
                    $sudah[(string) $sidik] = true;
                }
            }

            $baru = 0;

            foreach ($baris as $sidik => $isian) {
                if (isset($sudah[$sidik])) {
                    continue;
                }

                MutasiBank::query()->create([...$isian, 'IdAkun' => $idAkun, 'IdImporMutasiBank' => $impor->Id, 'SidikBaris' => $sidik, 'Status' => StatusMutasiBank::BelumCocok]);
                $baru++;
            }

            $impor->fill(['Baru' => $baru, 'Duplikat' => count($baris) - $baru])->save();
            $this->audit->Catat('mutasi-bank.impor', $impor, nilaiBaru: ['JumlahBaris' => $jumlah, 'Baru' => $baru, 'Duplikat' => count($baris) - $baru, 'Bermasalah' => count($bermasalah)], idPengguna: $idPengguna);

            return ['Impor' => $impor, 'Bermasalah' => array_slice($bermasalah, 0, 100)];
        });
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: list<array{Baris: int, Pesan: string}>, 2: int}
     */
    private function BacaBaris(string $path, string $format, ?string $pemisah): array
    {
        $kolom = null;
        $baris = [];
        $kembar = [];
        $bermasalah = [];
        $jumlah = 0;

        foreach ($this->pembaca->BacaBaris($path, $format, $pemisah) as $nomor => $sel) {
            if ($kolom === null) {
                $kolom = self::CobaPetakanJudul($sel);

                if ($kolom === null && $nomor >= 20) {
                    break;
                }

                continue;
            }

            $ambil = fn (string $bidang): string => isset($kolom[$bidang]) ? trim($sel[$kolom[$bidang]] ?? '') : '';

            try {
                $tanggal = PenguraiMutasiBank::UraiTanggal($ambil('Tanggal'));
            } catch (PelanggaranAturanBisnis) {
                continue; // Saldo awal, total, catatan kaki.
            }

            $jumlah++;

            if ($jumlah > self::MAKSIMAL_BARIS) {
                throw new PelanggaranAturanBisnis('BarisTerlaluBanyak', 'Paling banyak 5.000 mutasi per berkas. Unduh rekening koran per bulan.', 'Berkas');
            }

            try {
                [$masuk, $keluar] = self::AmbilNominal($ambil);
                $saldo = PenguraiMutasiBank::UraiNominal($ambil('Saldo'));
            } catch (PelanggaranAturanBisnis $galat) {
                $bermasalah[] = ['Baris' => (int) $nomor, 'Pesan' => $galat->getMessage()];

                continue;
            }

            if ($masuk === '0.00' && $keluar === '0.00') {
                continue;
            }

            $keterangan = mb_substr($ambil('Keterangan') === '' ? '-' : preg_replace('/\s+/', ' ', $ambil('Keterangan')) ?? '-', 0, 255);
            $saldoTeks = $saldo === null ? null : ($saldo['Tanda'] < 0 ? '-' : '').$saldo['Nilai'];
            $dasar = implode('|', [$tanggal, $keterangan, $masuk, $keluar, $saldoTeks ?? '']);
            $kembar[$dasar] = ($kembar[$dasar] ?? 0) + 1;
            $baris[hash('sha256', $dasar.'|'.$kembar[$dasar])] = ['Tanggal' => $tanggal, 'Keterangan' => $keterangan, 'Masuk' => $masuk, 'Keluar' => $keluar, 'Saldo' => $saldoTeks];
        }

        if ($kolom === null) {
            throw new PelanggaranAturanBisnis('KolomWajibTidakAda', 'Kolom tanggal dan nominal (Debit/Kredit atau Jumlah) tidak ditemukan. Pastikan berkas adalah rekening koran dari internet banking.', 'Berkas');
        }

        return [$baris, $bermasalah, $jumlah];
    }

    /**
     * @param  callable(string): string  $ambil
     * @return array{0: string, 1: string} masuk, keluar
     */
    private static function AmbilNominal(callable $ambil): array
    {
        $masuk = PenguraiMutasiBank::UraiNominal($ambil('Masuk'));
        $keluar = PenguraiMutasiBank::UraiNominal($ambil('Keluar'));

        if ($masuk !== null || $keluar !== null) {
            return [$masuk['Nilai'] ?? '0.00', $keluar['Nilai'] ?? '0.00'];
        }

        $jumlah = PenguraiMutasiBank::UraiNominal($ambil('Jumlah'));

        if ($jumlah === null) {
            return ['0.00', '0.00'];
        }

        return $jumlah['Tanda'] < 0 ? ['0.00', $jumlah['Nilai']] : [$jumlah['Nilai'], '0.00'];
    }

    /**
     * Peta kolom bila baris ini judul (memuat tanggal + nominal), selain itu null.
     *
     * @param  list<string>  $judul
     * @return array<string, int>|null
     */
    private static function CobaPetakanJudul(array $judul): ?array
    {
        $peta = [];

        foreach ($judul as $indeks => $teks) {
            $kunci = (string) preg_replace('/[^a-z]/', '', mb_strtolower($teks));

            foreach (self::SINONIM as $bidang => $daftar) {
                if (! isset($peta[$bidang]) && in_array($kunci, $daftar, true)) {
                    $peta[$bidang] = $indeks;

                    break;
                }
            }
        }

        $adaNominal = isset($peta['Masuk']) || isset($peta['Keluar']) || isset($peta['Jumlah']);

        return isset($peta['Tanggal']) && $adaNominal ? $peta : null;
    }
}
