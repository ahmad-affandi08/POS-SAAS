<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Model\PesanKeluar;

/**
 * K3: status kiriman struk digital untuk POS (`GET /api/pos/v1/pesan-keluar/{uuid}`), lewat scope tenant perangkat.
 * Tidak pernah memuat tujuan pelanggan.
 */
final class HasilPesanKeluar
{
    /**
     * @return array{Uuid: string, Status: string, PesanGalat: string|null}|null
     */
    public function Ambil(string $uuid): ?array
    {
        $pesan = PesanKeluar::query()->where('Uuid', $uuid)->first();

        return $pesan === null ? null : self::KeLarik($pesan);
    }

    /**
     * @return array{Uuid: string, Status: string, PesanGalat: string|null}
     */
    public static function KeLarik(PesanKeluar $pesan): array
    {
        return ['Uuid' => $pesan->Uuid, 'Status' => $pesan->Status->value, 'PesanGalat' => $pesan->PesanGalat];
    }
}
