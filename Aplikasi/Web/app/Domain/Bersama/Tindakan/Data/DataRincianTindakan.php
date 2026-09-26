<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Data;

/** Satu dokumen di rincian butir tindakan (misal satu penjualan yang perlu dicek). */
final readonly class DataRincianTindakan
{
    public function __construct(
        public string $uuid,
        public string $judul,
        public ?string $keterangan,
        public ?string $tanggal,
        public ?string $tautan,
    ) {}

    /**
     * @return array{Uuid: string, Judul: string, Keterangan: string|null, Tanggal: string|null, Tautan: string|null}
     */
    public function KeLarik(): array
    {
        return [
            'Uuid' => $this->uuid,
            'Judul' => $this->judul,
            'Keterangan' => $this->keterangan,
            'Tanggal' => $this->tanggal,
            'Tautan' => $this->tautan,
        ];
    }
}
