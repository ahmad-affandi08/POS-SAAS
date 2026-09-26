<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Penjualan\Enum\StatusPesananSendiri;
use App\Domain\Penjualan\Model\PesananSendiri;
use Carbon\CarbonImmutable;

/**
 * F-17: pesanan QR yang tidak diproses staf dalam 30 menit menjadi `Kedaluwarsa`. Ditandai secara malas (lazy) setiap
 * kali pesanan outlet/meja dibaca (daftar POS, polling tamu, batas pesanan tertunda, terima/tolak), sehingga tidak
 * perlu penjadwal; pesanan yang tidak pernah dibaca lagi tetap `MenungguKonfirmasi` di basis data tetapi tidak
 * pernah tampil maupun bisa diproses sebagai pesanan aktif.
 */
final class PenandaKedaluwarsaPesananSendiri
{
    public const MENIT_BERLAKU = 30;

    public static function AmbilBatas(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes(self::MENIT_BERLAKU);
    }

    public static function CekKedaluwarsa(PesananSendiri $pesanan): bool
    {
        return $pesanan->Status === StatusPesananSendiri::MenungguKonfirmasi
            && $pesanan->DibuatPada !== null
            && $pesanan->DibuatPada->lessThan(self::AmbilBatas());
    }

    public function TandaiOutlet(int $idOutlet): void
    {
        $this->Tandai('IdOutlet', $idOutlet);
    }

    public function TandaiMeja(int $idMeja): void
    {
        $this->Tandai('IdMeja', $idMeja);
    }

    private function Tandai(string $kolom, int $id): void
    {
        PesananSendiri::query()
            ->where($kolom, $id)
            ->where('Status', StatusPesananSendiri::MenungguKonfirmasi->value)
            ->where('DibuatPada', '<', self::AmbilBatas())
            ->update(['Status' => StatusPesananSendiri::Kedaluwarsa->value, 'DiubahPada' => now()]);
    }
}
