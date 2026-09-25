<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataPengaturanStruk;
use App\Domain\Tenant\Model\Tenant;

/**
 * Pengaturan struk tenant dari `Tenant.Pengaturan.Struk` (PRD v1.79). Nilai rusak kembali ke bawaan: saklar bawaan
 * aktif, teks terlalu panjang/kosong = null, baris kepala dipotong ke 3 baris sah.
 */
final class PengaturanStrukTenant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Ambil(?int $idTenant = null): DataPengaturanStruk
    {
        $pengaturan = Tenant::query()->whereKey($idTenant ?? $this->konteks->Wajib())->firstOrFail()->Pengaturan ?? [];
        $struk = is_array($pengaturan['Struk'] ?? null) ? $pengaturan['Struk'] : [];
        $saklar = static fn (string $kunci): bool => ($struk[$kunci] ?? true) !== false;

        return new DataPengaturanStruk(
            tampilkanLogo: $saklar('TampilkanLogo'),
            namaDicetak: self::AmbilTeks($struk['NamaDicetak'] ?? null, DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL),
            teksKepala: self::AmbilKepala($struk['TeksKepala'] ?? null),
            tampilkanAlamat: $saklar('TampilkanAlamat'),
            tampilkanTelepon: $saklar('TampilkanTelepon'),
            tampilkanNpwp: $saklar('TampilkanNpwp'),
            tampilkanKasir: $saklar('TampilkanKasir'),
            tampilkanPelanggan: $saklar('TampilkanPelanggan'),
            tampilkanHemat: $saklar('TampilkanHemat'),
            catatanKaki: self::AmbilTeks($struk['CatatanKaki'] ?? null, DataPengaturanStruk::PANJANG_CATATAN_KAKI_MAKSIMAL),
            teksPenutup: self::AmbilTeks($struk['TeksPenutup'] ?? null, DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL),
        );
    }

    private static function AmbilTeks(mixed $nilai, int $panjangMaksimal): ?string
    {
        if (! is_string($nilai)) {
            return null;
        }

        $teks = trim($nilai);

        return $teks === '' || mb_strlen($teks) > $panjangMaksimal ? null : $teks;
    }

    /**
     * @return list<string>
     */
    private static function AmbilKepala(mixed $nilai): array
    {
        if (! is_array($nilai)) {
            return [];
        }

        $baris = [];

        foreach ($nilai as $teks) {
            $teks = self::AmbilTeks($teks, DataPengaturanStruk::PANJANG_BARIS_MAKSIMAL);

            if ($teks !== null && count($baris) < DataPengaturanStruk::JUMLAH_TEKS_KEPALA_MAKSIMAL) {
                $baris[] = $teks;
            }
        }

        return $baris;
    }
}
