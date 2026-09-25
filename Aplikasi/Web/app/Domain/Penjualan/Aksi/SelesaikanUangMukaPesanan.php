<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Enum\CaraPenyelesaianUangMuka;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use App\Domain\Penjualan\Model\PesananPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Batalkan pre-order / selesaikan sisa uang mukanya (F-12 bagian 2, izin `akuntansi.kelola`). Pesanan `Dipesan`/`Siap`
 * menjadi `Dibatalkan`; pesanan `Diambil` dengan sisa DP (DP lebih besar dari tagihan saat diambil) tetap `Diambil`.
 * Sisa DP [cara]: `Dikembalikan` → Dr Uang Muka Pelanggan, Cr akun kas/bank pilihan; `Hangus` → Dr Uang Muka
 * Pelanggan, Cr Pendapatan Lain (dimensi outlet pesanan), bertanggal bisnis hari ini (periode harus terbuka). Alasan
 * 5–255 karakter. Audit `pesanan-penjualan.batalkan|selesaikan-uang-muka`.
 */
final class SelesaikanUangMukaPesanan
{
    /** Jurnal penyelesaian terpisah dari jurnal DP (J-07.3) pada sumber yang sama; sisa DP hanya bisa diselesaikan sekali. */
    public const KUNCI_JURNAL = 'Penyelesaian';

    public function __construct(
        private readonly DaftarAkunPilihan $akun,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PesananPenjualan $pesanan, CaraPenyelesaianUangMuka $cara, ?string $uuidAkun, string $alasan, int $idPengguna): PesananPenjualan
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan 5–255 karakter.', 'Alasan');
        }

        $idAkun = null;

        if ($cara === CaraPenyelesaianUangMuka::Dikembalikan) {
            $idAkun = ($uuidAkun === null ? null : $this->akun->CariKasBankDariUuid($uuidAkun))['Id']
                ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas/bank aktif untuk mengembalikan uang muka.', 'UuidAkun');
        }

        return DB::transaction(function () use ($pesanan, $cara, $idAkun, $alasan, $idPengguna): PesananPenjualan {
            $pesanan = PesananPenjualan::query()->whereKey($pesanan->Id)->lockForUpdate()->firstOrFail();
            $sisa = $pesanan->AmbilSisaUangMuka();
            $batal = $pesanan->Status->CekTerbuka();

            if (! $batal && ($pesanan->Status !== StatusPesananPenjualan::Diambil || $sisa->Bandingkan(Uang::Nol()) <= 0)) {
                throw new PelanggaranAturanBisnis('TidakAdaSisaUangMuka', "Pre-order {$pesanan->Nomor} berstatus {$pesanan->Status->AmbilLabel()} tanpa sisa uang muka.", 'Status');
            }

            $tanggal = $this->tanggalBisnis->Hitung($pesanan->IdOutlet);
            $this->penjagaPeriode->PastikanTerbuka($tanggal);
            $asal = $pesanan->Status;

            if ($sisa->Bandingkan(Uang::Nol()) > 0) {
                $kredit = $idAkun !== null
                    ? new DataBarisJurnal(null, $idAkun, $pesanan->IdOutlet, Uang::Nol(), $sisa, "Pengembalian uang muka {$pesanan->Nomor}")
                    : DataBarisJurnal::Kredit(PeranAkun::PendapatanLain, $sisa, $pesanan->IdOutlet, "Uang muka hangus {$pesanan->Nomor}");
                $pesanan->IdJurnalPenyelesaian = $this->postingJurnal->Jalankan(new DataJurnal(
                    jenisSumber: JenisSumberJurnal::PesananPenjualan,
                    idSumber: $pesanan->Id,
                    uuidSumber: $pesanan->Uuid,
                    nomorSumber: $pesanan->Nomor,
                    tanggal: CarbonImmutable::parse($tanggal->toDateString()),
                    keterangan: mb_substr(($cara === CaraPenyelesaianUangMuka::Dikembalikan ? 'Pengembalian' : 'Uang muka hangus')." pre-order {$pesanan->Nomor}: {$alasan}", 0, 255),
                    baris: [DataBarisJurnal::Debit(PeranAkun::UangMukaPelanggan, $sisa, $pesanan->IdOutlet, $pesanan->Nomor), $kredit],
                    idPengguna: $idPengguna,
                    kunciSumber: self::KUNCI_JURNAL,
                ))->idJurnal;
                if ($cara === CaraPenyelesaianUangMuka::Dikembalikan) {
                    $pesanan->UangMukaDikembalikan = Uang::Dari($pesanan->UangMukaDikembalikan)->Tambah($sisa)->KeString();
                } else {
                    $pesanan->UangMukaHangus = Uang::Dari($pesanan->UangMukaHangus)->Tambah($sisa)->KeString();
                }
            }

            if ($batal) {
                $pesanan->fill([
                    'Status' => StatusPesananPenjualan::Dibatalkan,
                    'DibatalkanPada' => CarbonImmutable::now(),
                    'AlasanBatal' => $alasan,
                    'IdPembatal' => $idPengguna,
                ]);
                $this->riwayat->Catat(PesananPenjualan::JENIS_DOKUMEN, $pesanan->Id, $asal->value, StatusPesananPenjualan::Dibatalkan->value, $idPengguna, $alasan);
            }

            $pesanan->save();
            $this->audit->Catat($batal ? 'pesanan-penjualan.batalkan' : 'pesanan-penjualan.selesaikan-uang-muka', $pesanan, ['Status' => $asal->value], [
                'Status' => $pesanan->Status->value,
                'Cara' => $cara->value,
                'Jumlah' => $sisa->KeString(),
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $pesanan;
        });
    }
}
