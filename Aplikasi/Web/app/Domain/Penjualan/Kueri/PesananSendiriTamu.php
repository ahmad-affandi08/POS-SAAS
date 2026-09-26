<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Layanan\PenandaKedaluwarsaPesananSendiri;
use App\Domain\Penjualan\Model\PesananSendiri;

/**
 * F-17: status pesanan QR untuk halaman tamu (polling tiap 5 detik). Hanya pesanan meja token yang sama; tidak
 * membuka data staf/perangkat pemroses.
 */
final class PesananSendiriTamu
{
    public function __construct(private readonly PenandaKedaluwarsaPesananSendiri $kedaluwarsa) {}

    /**
     * @return array{Uuid: string, Nomor: string, Status: string, Baris: list<array<string, mixed>>, Subtotal: string, AlasanTolak: string|null}|null
     */
    public function Ambil(int $idMeja, string $uuid): ?array
    {
        $this->kedaluwarsa->TandaiMeja($idMeja);
        $pesanan = PesananSendiri::query()->where('IdMeja', $idMeja)->where('Uuid', $uuid)->first();

        return $pesanan === null ? null : self::Susun($pesanan);
    }

    /**
     * @return array{Uuid: string, Nomor: string, Status: string, Baris: list<array<string, mixed>>, Subtotal: string, AlasanTolak: string|null}
     */
    public static function Susun(PesananSendiri $pesanan): array
    {
        return [
            'Uuid' => $pesanan->Uuid,
            'Nomor' => $pesanan->Nomor,
            'Status' => $pesanan->Status->value,
            'Baris' => $pesanan->Baris,
            'Subtotal' => $pesanan->Subtotal,
            'AlasanTolak' => $pesanan->AlasanTolak,
        ];
    }
}
