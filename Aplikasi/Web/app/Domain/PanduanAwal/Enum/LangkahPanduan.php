<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Enum;

/**
 * Langkah wizard panduan awal (F-01 langkah 1–6). Setiap langkah bisa dilewati dan dilanjutkan kapan saja; langkah 7
 * (selesai) dicatat terpisah di `ProgresPanduanAwal.SelesaiPada`.
 */
enum LangkahPanduan: string
{
    case ProfilUsaha = 'ProfilUsaha';
    case Sektor = 'Sektor';
    case Pajak = 'Pajak';
    case Produk = 'Produk';
    case MetodePembayaran = 'MetodePembayaran';
    case Perangkat = 'Perangkat';

    /** Segmen URL kebab-case (D-06), misal `/kelola/panduan-awal/metode-pembayaran`. */
    public function AmbilSlug(): string
    {
        return match ($this) {
            self::ProfilUsaha => 'profil-usaha',
            self::Sektor => 'sektor',
            self::Pajak => 'pajak',
            self::Produk => 'produk',
            self::MetodePembayaran => 'metode-pembayaran',
            self::Perangkat => 'perangkat',
        };
    }

    public static function DariSlug(string $slug): ?self
    {
        foreach (self::cases() as $langkah) {
            if ($langkah->AmbilSlug() === $slug) {
                return $langkah;
            }
        }

        return null;
    }

    public function AmbilJudul(): string
    {
        return match ($this) {
            self::ProfilUsaha => 'Profil usaha',
            self::Sektor => 'Jenis usaha & template',
            self::Pajak => 'Pajak',
            self::Produk => 'Produk awal',
            self::MetodePembayaran => 'Metode pembayaran',
            self::Perangkat => 'Perangkat kasir',
        };
    }

    public function AmbilBerikutnya(): ?self
    {
        $semua = self::cases();
        $posisi = array_search($this, $semua, true);

        return is_int($posisi) ? ($semua[$posisi + 1] ?? null) : null;
    }

    /** Nama rute halaman langkah, misal `kelola.panduan-awal.pajak`. */
    public function AmbilNamaRute(): string
    {
        return 'kelola.panduan-awal.'.$this->AmbilSlug();
    }
}
