<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Data;

use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;

/**
 * Hasil satu item outbox. `galat` terisi hanya untuk `Ditolak`: `{Kode, Pesan, Bidang, Detail}`.
 */
final readonly class HasilItemSinkron
{
    /**
     * @param  array{Kode: string, Pesan: string, Bidang: string, Detail: array<string, mixed>}|null  $galat
     */
    public function __construct(
        public string $uuid,
        public string $jenis,
        public StatusItemSinkron $status,
        public ?array $galat = null,
    ) {}

    /**
     * @return array{Uuid: string, Jenis: string, Status: string, Galat: array{Kode: string, Pesan: string, Bidang: string, Detail: array<string, mixed>}|null}
     */
    public function KeArray(): array
    {
        return ['Uuid' => $this->uuid, 'Jenis' => $this->jenis, 'Status' => $this->status->value, 'Galat' => $this->galat];
    }
}
