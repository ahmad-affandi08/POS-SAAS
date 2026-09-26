<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Penjualan\Enum\StatusPesananSendiri;
use App\Domain\Penjualan\Layanan\PenandaKedaluwarsaPesananSendiri;
use App\Domain\Penjualan\Model\PesananSendiri;

/**
 * F-17: pesanan QR `MenungguKonfirmasi` outlet perangkat untuk POS (`GET /api/pos/v1/pesan-sendiri`), terlama dulu.
 * Pesanan lewat 30 menit ditandai `Kedaluwarsa` lebih dulu sehingga tidak ikut.
 */
final class PesananSendiriOutlet
{
    public function __construct(
        private readonly PenandaKedaluwarsaPesananSendiri $kedaluwarsa,
        private readonly MejaOutlet $meja,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilMenunggu(int $idOutlet): array
    {
        $this->kedaluwarsa->TandaiOutlet($idOutlet);
        $pesanan = PesananSendiri::query()
            ->where('IdOutlet', $idOutlet)
            ->where('Status', StatusPesananSendiri::MenungguKonfirmasi->value)
            ->orderBy('DibuatPada')
            ->orderBy('Id')
            ->get();
        $meja = $this->meja->AmbilPerId(array_values(array_unique($pesanan->pluck('IdMeja')->all())));

        return array_values($pesanan->map(fn (PesananSendiri $p): array => [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'UuidMeja' => $meja[$p->IdMeja]['Uuid'] ?? null,
            'NamaMeja' => $meja[$p->IdMeja]['Nama'] ?? null,
            'NamaPemesan' => $p->NamaPemesan,
            'Catatan' => $p->Catatan,
            'DibuatPada' => $p->DibuatPada?->toIso8601ZuluString(),
            'Subtotal' => $p->Subtotal,
            'Baris' => $p->Baris,
        ])->all());
    }
}
