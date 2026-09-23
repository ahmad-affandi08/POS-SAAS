<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Enum\JenisNomorUrutKatalog;
use App\Domain\Katalog\Model\Produk;

/**
 * SKU otomatis per tenant (BR-03.1, DesainF03 C.5): `config('katalog.Sku.Awalan')` + nomor urut 6 digit
 * (`PRD-000001`, tumbuh melewati 6 digit dengan sendirinya). Nomor yang sudah dipakai SKU manual dilewati.
 * Dipanggil di dalam transaksi Aksi.
 */
final class PembuatSku
{
    public function __construct(private readonly PenghitungNomorUrutKatalog $penghitung) {}

    public function Buat(): string
    {
        $awalan = (string) config('katalog.Sku.Awalan', 'PRD-');

        do {
            $sku = $awalan.str_pad((string) $this->penghitung->AmbilBerikutnya(JenisNomorUrutKatalog::Sku), 6, '0', STR_PAD_LEFT);
        } while (self::CekSkuTerpakai($sku));

        return $sku;
    }

    /** SKU sudah dipakai produk lain di tenant aktif (termasuk produk diarsipkan; produk terhapus melepas SKU-nya). */
    public static function CekSkuTerpakai(string $sku, ?int $kecualiIdProduk = null): bool
    {
        return Produk::query()->withTrashed()
            ->where('Sku', $sku)
            ->when($kecualiIdProduk !== null, fn ($kueri) => $kueri->where('Id', '!=', $kecualiIdProduk))
            ->exists();
    }
}
