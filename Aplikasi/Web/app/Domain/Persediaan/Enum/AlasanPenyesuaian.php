<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Enum;

use App\Domain\Akuntansi\Enum\PeranAkun;

/**
 * Alasan penyesuaian stok (F-05b, PRD Rincian F-05b). Hanya `Lainnya` yang boleh menambah stok (barang ditemukan,
 * salah catat); alasan lain hanya mengurangi. Rusak, Hilang, dan Kedaluwarsa dicatat sebagai mutasi `Susut` ke akun
 * `SusutPersediaan` (J-05.4); Sampel, KonsumsiInternal, dan Lainnya sebagai `PenyesuaianKeluar` ke `SelisihHpp` sampai
 * peran beban promosi/konsumsi internal tersedia (§25 no. 16b). Penyesuaian masuk selalu ke `SelisihHpp` (J-05.5).
 * `Lainnya` wajib keterangan.
 */
enum AlasanPenyesuaian: string
{
    case Rusak = 'Rusak';
    case Hilang = 'Hilang';
    case Kedaluwarsa = 'Kedaluwarsa';
    case Sampel = 'Sampel';
    case KonsumsiInternal = 'KonsumsiInternal';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Rusak => 'Rusak',
            self::Hilang => 'Hilang',
            self::Kedaluwarsa => 'Kedaluwarsa',
            self::Sampel => 'Sampel',
            self::KonsumsiInternal => 'Konsumsi internal',
            self::Lainnya => 'Lainnya',
        };
    }

    public function CekBolehMasuk(): bool
    {
        return $this === self::Lainnya;
    }

    public function CekWajibKeterangan(): bool
    {
        return $this === self::Lainnya;
    }

    public function AmbilJenisMutasiKeluar(): JenisMutasi
    {
        return $this->CekSusut() ? JenisMutasi::Susut : JenisMutasi::PenyesuaianKeluar;
    }

    public function AmbilPeranLawanKeluar(): PeranAkun
    {
        return $this->CekSusut() ? PeranAkun::SusutPersediaan : PeranAkun::SelisihHpp;
    }

    private function CekSusut(): bool
    {
        return in_array($this, [self::Rusak, self::Hilang, self::Kedaluwarsa], true);
    }
}
