<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Data;

use App\Domain\Pemenuhan\Enum\SumberReservasi;

/** Masukan reservasi baru (F-07 mode service). `jam` = HH:MM zona waktu outlet; staf null = siapa saja yang kosong. */
final readonly class DataReservasi
{
    public function __construct(
        public int $idOutlet,
        public string $uuidLayanan,
        public string $tanggal,
        public string $jam,
        public ?string $uuidStaf,
        public string $namaPelanggan,
        public string $noHp,
        public ?string $catatan,
        public SumberReservasi $sumber,
        public ?int $idPengguna = null,
    ) {}
}
