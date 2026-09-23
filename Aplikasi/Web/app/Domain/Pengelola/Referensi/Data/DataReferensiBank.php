<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Data;

use App\Domain\Referensi\Enum\JenisReferensiBank;

final readonly class DataReferensiBank
{
    public function __construct(
        public string $kode,
        public string $nama,
        public JenisReferensiBank $jenis,
        public bool $aktif,
    ) {}

    /**
     * @return array{Kode: string, Nama: string, Jenis: string, Aktif: bool}
     */
    public function KeLarik(): array
    {
        return ['Kode' => $this->kode, 'Nama' => $this->nama, 'Jenis' => $this->jenis->value, 'Aktif' => $this->aktif];
    }
}
