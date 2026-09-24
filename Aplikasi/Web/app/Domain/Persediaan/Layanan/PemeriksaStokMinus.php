<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Tenant\Data\DataPengaturanPersediaan;

/**
 * Aturan stok minus BR-05.2 (DesainF05a C.4): boleh hanya bila Pelacakan = Tidak dan
 * (Produk.BolehMinus ?? Tenant.StokBolehMinus). Produk batch/seri tidak pernah boleh minus.
 */
final class PemeriksaStokMinus
{
    public function CekBolehMinus(DataInfoProdukStok $produk, DataPengaturanPersediaan $pengaturan): bool
    {
        return $produk->pelacakan === PelacakanProduk::Tidak && ($produk->bolehMinus ?? $pengaturan->stokBolehMinus);
    }

    /**
     * Menolak `BR-05.2` bila `tersedia − diminta < 0` dan produk tidak boleh minus. `diminta` = besaran keluar (> 0).
     */
    public function Pastikan(DataInfoProdukStok $produk, DataInfoGudang $gudang, DataPengaturanPersediaan $pengaturan, Kuantitas $tersedia, Kuantitas $diminta): void
    {
        if (! $tersedia->Kurangi($diminta)->BernilaiNegatif() || $this->CekBolehMinus($produk, $pengaturan)) {
            return;
        }

        throw new PelanggaranAturanBisnis(
            'BR-05.2',
            "Stok {$produk->nama} di {$gudang->nama} tidak cukup: tersedia ".self::FormatJumlah($tersedia).', dibutuhkan '.self::FormatJumlah($diminta).'.',
            'Jumlah',
            detail: [
                'UuidProduk' => $produk->uuid,
                'UuidGudang' => $gudang->uuid,
                'Tersedia' => $tersedia->KeString(),
                'Diminta' => $diminta->KeString(),
            ],
        );
    }

    /** "12", "2,5", "-3" (tanpa nol di belakang, koma desimal Indonesia). */
    public static function FormatJumlah(Kuantitas $jumlah): string
    {
        return str_replace('.', ',', (string) $jumlah->KeDesimal()->strippedOfTrailingZeros());
    }
}
