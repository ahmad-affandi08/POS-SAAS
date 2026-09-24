<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

use App\Domain\Organisasi\Enum\JenisGudang;

/**
 * Ringkasan lokasi stok untuk domain lain (DesainF05a C.1, `InfoGudang`). `aktif` = status Aktif (bukan diarsipkan).
 */
final readonly class DataInfoGudang
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $kode,
        public string $nama,
        public JenisGudang $jenis,
        public ?int $idOutlet,
        public ?string $namaOutlet,
        public bool $aktif,
    ) {}
}
