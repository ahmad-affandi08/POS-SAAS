<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

/**
 * Meja di balik token QR beserta apakah tamu boleh memesan (F-17): meja & outlet aktif, sakelar outlet hidup, fitur
 * paket `kanal.self-order` aktif di outlet, dan langganan boleh bertransaksi.
 */
final readonly class DataKonteksPesanSendiri
{
    public function __construct(
        public int $idTenant,
        public int $idOutlet,
        public string $kodeOutlet,
        public string $namaOutlet,
        public string $zonaWaktu,
        public int $idMeja,
        public string $uuidMeja,
        public string $namaMeja,
        public bool $aktif,
    ) {}
}
