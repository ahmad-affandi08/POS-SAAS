<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataPengaturanStruk;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah pengaturan struk tenant (PLT-06, PRD v1.79): satu pengaturan untuk semua outlet, ditulis ke
 * `Tenant.Pengaturan.Struk`. Berlaku di struk berikutnya setelah perangkat kasir memperbarui data. Tanpa perubahan =
 * tidak ada yang ditulis. Audit `struk.pengaturan.ubah`.
 */
final class UbahPengaturanStruk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PengaturanStrukTenant $pengaturan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPengaturanStruk $baru): void
    {
        self::Validasi($baru);

        DB::transaction(function () use ($baru): void {
            $tenant = $this->penguncian->Kunci($this->konteks->Wajib());
            $nilaiLama = $this->pengaturan->Ambil($tenant->Id)->KeLarik();
            $nilaiBaru = $baru->KeLarik();

            if ($nilaiLama === $nilaiBaru) {
                return;
            }

            $pengaturan = $tenant->Pengaturan ?? [];
            $pengaturan['Struk'] = $nilaiBaru;
            $tenant->Pengaturan = $pengaturan;
            $tenant->save();

            $this->audit->Catat('struk.pengaturan.ubah', nilaiLama: $nilaiLama, nilaiBaru: $nilaiBaru);
        });
    }

    private static function Validasi(DataPengaturanStruk $data): void
    {
        $baris = DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL;

        foreach (['NamaDicetak' => $data->namaDicetak, 'TeksPenutup' => $data->teksPenutup] as $bidang => $teks) {
            if ($teks !== null && mb_strlen($teks) > $baris) {
                throw new PelanggaranAturanBisnis('TeksStrukTerlaluPanjang', "Teks ini paling banyak {$baris} karakter agar muat satu baris struk.", $bidang);
            }
        }

        if (count($data->teksKepala) > DataPengaturanStruk::JUMLAH_TEKS_KEPALA_MAKSIMAL) {
            throw new PelanggaranAturanBisnis('TeksKepalaTerlaluBanyak', 'Teks kepala struk paling banyak '.DataPengaturanStruk::JUMLAH_TEKS_KEPALA_MAKSIMAL.' baris.', 'TeksKepala');
        }

        foreach ($data->teksKepala as $i => $teks) {
            if ($teks === '' || mb_strlen($teks) > $baris) {
                throw new PelanggaranAturanBisnis('TeksStrukTerlaluPanjang', "Setiap baris kepala struk berisi 1 sampai {$baris} karakter.", "TeksKepala.{$i}");
            }
        }

        if ($data->catatanKaki !== null && mb_strlen($data->catatanKaki) > DataPengaturanStruk::PANJANG_CATATAN_KAKI_MAKSIMAL) {
            throw new PelanggaranAturanBisnis('TeksStrukTerlaluPanjang', 'Catatan kaki struk paling banyak '.DataPengaturanStruk::PANJANG_CATATAN_KAKI_MAKSIMAL.' karakter.', 'CatatanKaki');
        }
    }
}
