<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataAkun;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\AturanAkun;
use App\Domain\Akuntansi\Layanan\PenilaiPemakaianAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-13a bagan akun: ubah nama, kode, tipe, sifat kontra, dan penanda kas/bank. Kode & tipe terkunci begitu akun punya
 * jurnal (`TipeAkunTerkunci`); tipe juga harus tetap sama dengan induk/anaknya dan cocok dengan peran yang
 * dipetakan ke akun ini (aturan BR-P03.3 lewat `PeranAkun::PeriksaAkun`). LogAudit `akun.ubah` (nilai lama/baru).
 */
final class UbahAkun
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PenilaiPemakaianAkun $pemakaian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis TipeAkunTerkunci, TipeAkunBedaInduk, AkunPunyaAnak, PemetaanAkunTidakCocok,
     *                                 AkunKasMasihDipetakan, KodeAkunTidakSesuaiTipe, KodeAkunSudahAda, KasBankHanyaAset
     */
    public function Jalankan(Akun $akun, DataAkun $data): Akun
    {
        return DB::transaction(function () use ($akun, $data): Akun {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $akun = Akun::query()->whereKey($akun->Id)->lockForUpdate()->firstOrFail();
            $tipeBerubah = $data->tipe !== $akun->Jenis || $data->kontra !== $akun->CekKontra();
            $kodeBerubah = $data->kode !== $akun->Kode;

            if (($tipeBerubah || $kodeBerubah) && $this->pemakaian->CekAdaJurnal($akun->Id)) {
                throw new PelanggaranAturanBisnis(
                    'TipeAkunTerkunci',
                    "Akun {$akun->Kode} sudah punya jurnal, jadi kode, tipe, dan sifat kontranya tidak bisa diubah. Buat akun baru bila perlu.",
                    $tipeBerubah ? 'Jenis' : 'Kode',
                );
            }

            $peranDipetakan = $this->pemakaian->AmbilPeranDipetakan($akun->Id);

            if ($tipeBerubah) {
                $this->PastikanTipeBolehBerubah($akun, $data, $peranDipetakan);
            }

            AturanAkun::Periksa($data, $akun->Id);

            if (! $data->kasBank && array_filter($peranDipetakan, fn (PeranAkun $p): bool => $p->CekPeranKasBank()) !== []) {
                throw new PelanggaranAturanBisnis(
                    'AkunKasMasihDipetakan',
                    "Akun {$akun->Kode} dipetakan untuk peran kas/bank, jadi harus tetap ditandai sebagai akun kas/bank.",
                    'KasBank',
                );
            }

            $lama = self::Potret($akun);
            $akun->Kode = $data->kode;
            $akun->Nama = trim($data->nama);
            $akun->Jenis = $data->tipe;
            $akun->SaldoNormal = $data->tipe->AmbilSaldoNormal($data->kontra);
            $akun->KasBank = $data->kasBank;
            $akun->save();
            $baru = self::Potret($akun);

            if ($lama !== $baru) {
                $this->audit->Catat('akun.ubah', $akun, nilaiLama: $lama, nilaiBaru: $baru);
            }

            return $akun;
        });
    }

    /**
     * @param  list<PeranAkun>  $peranDipetakan
     */
    private function PastikanTipeBolehBerubah(Akun $akun, DataAkun $data, array $peranDipetakan): void
    {
        $induk = $akun->IdInduk === null ? null : Akun::query()->whereKey($akun->IdInduk)->first(['Jenis']);

        if ($induk instanceof Akun && $induk->Jenis !== $data->tipe) {
            throw new PelanggaranAturanBisnis('TipeAkunBedaInduk', "Akun anak harus bertipe sama dengan induknya ({$induk->Jenis->AmbilLabel()}).", 'Jenis');
        }

        if ($data->tipe !== $akun->Jenis && Akun::query()->where('IdInduk', $akun->Id)->exists()) {
            throw new PelanggaranAturanBisnis('AkunPunyaAnak', "Akun {$akun->Kode} punya akun anak, jadi tipenya tidak bisa diubah.", 'Jenis');
        }

        foreach ($peranDipetakan as $peran) {
            $galat = $peran->PeriksaAkun($data->tipe, $data->kontra, $data->kode);

            if ($galat !== []) {
                throw new PelanggaranAturanBisnis('PemetaanAkunTidakCocok', implode(' ', $galat).' Ganti pemetaan akun dulu.', 'Jenis');
            }
        }
    }

    /**
     * @return array{Kode: string, Nama: string, Jenis: string, SaldoNormal: string, KasBank: bool}
     */
    private static function Potret(Akun $akun): array
    {
        return [
            'Kode' => $akun->Kode,
            'Nama' => $akun->Nama,
            'Jenis' => $akun->Jenis->value,
            'SaldoNormal' => $akun->SaldoNormal->value,
            'KasBank' => $akun->KasBank,
        ];
    }
}
