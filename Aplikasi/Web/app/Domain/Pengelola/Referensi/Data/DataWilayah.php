<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Data;

use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;

final readonly class DataWilayah
{
    public function __construct(
        public string $kode,
        public string $nama,
        public TingkatWilayah $tingkat,
        public ?string $kodeInduk,
        public ZonaWaktu $zonaWaktu,
    ) {}

    /**
     * @return array{Kode: string, Nama: string, Tingkat: string, KodeInduk: string|null, ZonaWaktu: string}
     */
    public function KeLarik(): array
    {
        return [
            'Kode' => $this->kode,
            'Nama' => $this->nama,
            'Tingkat' => $this->tingkat->value,
            'KodeInduk' => $this->kodeInduk,
            'ZonaWaktu' => $this->zonaWaktu->value,
        ];
    }
}
