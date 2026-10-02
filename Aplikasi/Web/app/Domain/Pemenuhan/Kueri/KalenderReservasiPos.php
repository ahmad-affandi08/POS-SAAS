<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\LayananReservasi;

/**
 * K-20: kalender reservasi satu tanggal di outlet perangkat untuk aplikasi kasir: layanan yang bisa dipesan (durasi &
 * harga), staf yang bekerja hari itu beserta jam kerjanya (`JadwalKerja`), dan reservasi hari itu (bentuk
 * `ReservasiPos`). Kasir menyusun blok sibuk/kosong per staf dari data ini; slot pasti tetap dari `SlotReservasi`.
 */
final class KalenderReservasiPos
{
    public function __construct(
        private readonly LayananReservasi $layanan,
        private readonly JadwalStafReservasi $staf,
        private readonly ReservasiPos $reservasi,
    ) {}

    /**
     * @return array{Tanggal: string, Layanan: list<array{Uuid: string, Nama: string, DurasiMenit: int, Harga: string|null}>, Staf: list<array{Uuid: string, Nama: string, JamMulai: string, JamSelesai: string}>, Reservasi: list<array<string, mixed>>}
     */
    public function Ambil(int $idOutlet, string $tanggal): array
    {
        return [
            'Tanggal' => $tanggal,
            'Layanan' => array_map(fn (array $l): array => [
                'Uuid' => $l['Uuid'],
                'Nama' => $l['Nama'],
                'DurasiMenit' => $l['DurasiMenit'],
                'Harga' => $l['Harga'],
            ], $this->layanan->Ambil()),
            'Staf' => array_map(fn (array $s): array => [
                'Uuid' => $s['Uuid'],
                'Nama' => $s['Nama'],
                'JamMulai' => $s['JamMulai'],
                'JamSelesai' => $s['JamSelesai'],
            ], $this->staf->Ambil($idOutlet, $tanggal)),
            'Reservasi' => $this->reservasi->Ambil($idOutlet, $tanggal),
        ];
    }
}
