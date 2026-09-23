<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Peran & akses outlet untuk undangan atau anggota (F-02 langkah 3). Tanpa `semuaOutlet`, minimal satu outlet.
 */
final readonly class DataAksesAnggota
{
    /**
     * @param  list<string>  $uuidOutlet
     */
    public function __construct(
        public string $uuidPeran,
        public bool $semuaOutlet,
        public array $uuidOutlet,
    ) {}
}
