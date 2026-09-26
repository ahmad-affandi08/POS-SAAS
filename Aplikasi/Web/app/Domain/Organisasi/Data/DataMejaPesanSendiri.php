<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Meja & outlet di balik token QR pesan sendiri (F-17), untuk domain lain tanpa membaca tabel Organisasi.
 * `uuidOutlet` & `kodeKota` (tarif pajak daerah) untuk estimasi total tamu (PRD v2.06).
 * `bisaDipesan` = meja & outlet aktif dan sakelar `Outlet.PesanSendiriAktif` hidup (fitur paket diperiksa pemanggil).
 */
final readonly class DataMejaPesanSendiri
{
    public function __construct(
        public int $idMeja,
        public string $uuidMeja,
        public string $namaMeja,
        public int $idOutlet,
        public string $kodeOutlet,
        public string $namaOutlet,
        public string $zonaWaktu,
        public bool $bisaDipesan,
        public string $uuidOutlet = '',
        public ?string $kodeKota = null,
    ) {}
}
