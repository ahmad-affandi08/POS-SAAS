<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Model\Tenant;

/**
 * Barcode timbangan tenant aktif (§9.3, v3.55) dari `Tenant.Pengaturan.BarcodeTimbangan`:
 * `{Aktif, Awalan: ["27", "28"], Nilai: "Berat"|"Harga"}`. Format EAN-13 `AA PPPPP NNNNN C`: awalan 2 digit, kode
 * produk 5 digit (barcode produk di katalog = 7 digit pertama), nilai 5 digit (gram atau Rupiah), digit cek.
 * Awalan `20` tidak boleh dipakai karena barcode internal produk berawalan 20 (F-03). Nilai rusak kembali ke bawaan
 * (tidak aktif).
 */
final class PengaturanBarcodeTimbanganTenant
{
    public const NILAI_BERAT = 'Berat';

    public const NILAI_HARGA = 'Harga';

    /** Awalan yang boleh dipakai barcode timbangan (GS1 in-store 21–29; 20 dipakai barcode internal produk). */
    public const AWALAN_BOLEH = ['21', '22', '23', '24', '25', '26', '27', '28', '29'];

    public function __construct(private readonly KonteksTenant $konteks) {}

    /**
     * @return array{Aktif: bool, Awalan: list<string>, Nilai: string}
     */
    public function Ambil(): array
    {
        $nilai = (Tenant::query()->whereKey($this->konteks->Wajib())->firstOrFail()->Pengaturan ?? [])['BarcodeTimbangan'] ?? null;

        return self::Rapikan(is_array($nilai) ? $nilai : []);
    }

    /**
     * @param  array<mixed>  $nilai
     * @return array{Aktif: bool, Awalan: list<string>, Nilai: string}
     */
    public static function Rapikan(array $nilai): array
    {
        $awalan = is_array($nilai['Awalan'] ?? null)
            ? array_values(array_intersect(self::AWALAN_BOLEH, array_filter($nilai['Awalan'], 'is_string')))
            : [];

        return [
            'Aktif' => ($nilai['Aktif'] ?? false) === true && $awalan !== [],
            'Awalan' => $awalan === [] ? ['27'] : $awalan,
            'Nilai' => ($nilai['Nilai'] ?? null) === self::NILAI_HARGA ? self::NILAI_HARGA : self::NILAI_BERAT,
        ];
    }
}
