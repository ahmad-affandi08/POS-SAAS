<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Model\TiketLaundry;

/**
 * Laundry (§9.9): status cucian untuk halaman lacak publik (struk digital `/s/{kode}` dari QR label/nota). Hanya data
 * yang juga tercetak di nota; tanpa nomor HP.
 */
final class StatusLaundryPublik
{
    /**
     * @return array{Status: string, LabelStatus: string, Tahap: list<array{Status: string, Label: string, Selesai: bool}>, JenisLayanan: string, Berat: string|null, Item: list<array{Nama: string, Jumlah: int}>, Parfum: string|null, EstimasiSelesaiPada: string, SiapPada: string|null, DiambilPada: string|null}|null
     */
    public function AmbilUntukPenjualan(int $idPenjualan): ?array
    {
        $t = TiketLaundry::query()->where('IdPenjualan', $idPenjualan)->first();

        if ($t === null) {
            return null;
        }

        $tahap = [StatusLaundry::Diterima, StatusLaundry::Dicuci, StatusLaundry::Dikeringkan, StatusLaundry::Disetrika, StatusLaundry::Siap, StatusLaundry::Diambil];

        return [
            'Status' => $t->Status->value,
            'LabelStatus' => $t->Status->AmbilLabel(),
            'Tahap' => array_map(fn (StatusLaundry $s): array => [
                'Status' => $s->value,
                'Label' => $s->AmbilLabel(),
                'Selesai' => $t->Status !== StatusLaundry::Dibatalkan && $t->Status->AmbilUrutan() >= $s->AmbilUrutan(),
            ], $tahap),
            'JenisLayanan' => $t->JenisLayanan->value,
            'Berat' => $t->Berat,
            'Item' => $t->Item ?? [],
            'Parfum' => $t->Parfum,
            'EstimasiSelesaiPada' => $t->EstimasiSelesaiPada->toIso8601ZuluString(),
            'SiapPada' => $t->SiapPada?->toIso8601ZuluString(),
            'DiambilPada' => $t->DiambilPada?->toIso8601ZuluString(),
        ];
    }
}
