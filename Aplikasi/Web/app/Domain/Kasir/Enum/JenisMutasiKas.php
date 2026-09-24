<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Enum;

/**
 * Jenis mutasi kas non-penjualan dalam shift (PRD §15.3 `MutasiKas.Jenis`, F-06 langkah 4). Masuk & Keluar wajib
 * berkategori; Setoran (setor ke pemilik/brankas) tanpa kategori dan dijurnal ke Kas Brankas (J-11.3).
 */
enum JenisMutasiKas: string
{
    case Masuk = 'Masuk';
    case Keluar = 'Keluar';
    case Setoran = 'Setoran';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Kas masuk',
            self::Keluar => 'Kas keluar',
            self::Setoran => 'Setoran',
        };
    }

    /** Arah uang di laci: Masuk menambah, Keluar & Setoran mengurangi. */
    public function CekMenambahKas(): bool
    {
        return $this === self::Masuk;
    }

    public function AmbilJenisKategori(): ?JenisKategoriKas
    {
        return match ($this) {
            self::Masuk => JenisKategoriKas::Masuk,
            self::Keluar => JenisKategoriKas::Keluar,
            self::Setoran => null,
        };
    }
}
