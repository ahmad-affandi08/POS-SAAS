<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Penjualan\Enum\StatusTagihanQris;
use App\Domain\Penjualan\Layanan\PencariTagihanQris;
use App\Domain\Penjualan\Layanan\PenerapStatusTagihanQris;
use App\Domain\Penjualan\Model\TagihanQris;
use Carbon\CarbonImmutable;

/**
 * F-08 BR-08.5 "Cek Status": POS menanyakan status tagihan QRIS (polling). Masih `Menunggu` = gerbang ditanya paling
 * sering sekali per `PencariTagihanQris::DETIK_JEDA_CEK` detik per tagihan (cadangan webhook terlambat); lewat
 * `KedaluwarsaPada` + `MENIT_TENGGANG` tanpa kabar lunas = `Kedaluwarsa` lokal.
 */
final class CekStatusTagihanQrisPos
{
    public const MENIT_TENGGANG = 2;

    public function __construct(
        private readonly PencariTagihanQris $pencari,
        private readonly PenerapStatusTagihanQris $penerap,
    ) {}

    public function Jalankan(string $uuid, int $idOutlet): TagihanQris
    {
        $tagihan = $this->pencari->CekKeGerbang($this->pencari->CariDiOutlet($uuid, $idOutlet));

        if ($tagihan->Status === StatusTagihanQris::Menunggu
            && $tagihan->KedaluwarsaPada->lessThan(CarbonImmutable::now()->subMinutes(self::MENIT_TENGGANG))) {
            return $this->penerap->TandaiKedaluwarsa($tagihan->Id);
        }

        return $tagihan;
    }
}
