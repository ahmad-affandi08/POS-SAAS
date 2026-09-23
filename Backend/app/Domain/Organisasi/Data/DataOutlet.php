<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Isian outlet (F-02 langkah 1). Zona waktu ditentukan dari kota bila kota diisi; `zonaWaktu` (WIB/WITA/WIT)
 * hanya dipakai bila kota kosong. Profil pajak disimpan apa adanya, dihitung di F-03.
 */
final readonly class DataOutlet
{
    public function __construct(
        public string $nama,
        public string $kode,
        public string $uuidMerek,
        public ?string $alamat,
        public ?string $kodeKota,
        public string $zonaWaktu,
        public string $jamTutupBuku,
        public bool $pkp,
        public ?string $nitku,
        public bool $pungutPbjt,
    ) {}
}
