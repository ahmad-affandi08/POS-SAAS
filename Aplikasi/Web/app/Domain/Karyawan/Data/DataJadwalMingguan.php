<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Data;

use Carbon\CarbonImmutable;

/**
 * Jadwal satu minggu (Senin–Minggu) satu outlet (F-18). Tiap sel: karyawan, tanggal, jam `HH:mm`; jam kosong = hapus
 * jadwal hari itu.
 */
final readonly class DataJadwalMingguan
{
    /**
     * @param  list<array{UuidKaryawan: string, Tanggal: string, JamMulai: string|null, JamSelesai: string|null}>  $sel
     */
    public function __construct(
        public int $idOutlet,
        public CarbonImmutable $senin,
        public array $sel,
        public int $idPengguna,
    ) {}
}
