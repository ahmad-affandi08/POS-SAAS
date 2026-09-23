<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Data;

final readonly class DataSatuanStandar
{
    public function __construct(
        public string $kode,
        public string $nama,
        public string $simbol,
        public bool $bolehDesimal,
        public bool $aktif,
    ) {}

    /**
     * @return array{Kode: string, Nama: string, Simbol: string, BolehDesimal: bool, Aktif: bool}
     */
    public function KeLarik(): array
    {
        return [
            'Kode' => $this->kode,
            'Nama' => $this->nama,
            'Simbol' => $this->simbol,
            'BolehDesimal' => $this->bolehDesimal,
            'Aktif' => $this->aktif,
        ];
    }
}
