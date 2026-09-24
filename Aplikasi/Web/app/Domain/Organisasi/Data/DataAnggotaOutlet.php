<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Anggota aktif tenant yang punya akses ke satu outlet (F-06): identitas untuk domain lain (Kasir) tanpa membaca
 * tabel Organisasi, plus daftar kunci izinnya.
 */
final readonly class DataAnggotaOutlet
{
    /**
     * @param  list<string>  $izin
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $nama,
        public bool $pemilik,
        public array $izin,
    ) {}

    public function CekIzin(string $kunci): bool
    {
        return $this->pemilik || in_array($kunci, $this->izin, true);
    }
}
