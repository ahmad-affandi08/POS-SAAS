<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Model\Penjualan;

/**
 * Ringkasan penjualan per Id untuk dokumen domain lain yang ditagih lewat kasir (bengkel §9.10: perintah kerja &
 * riwayat servis kendaraan), supaya domain itu tidak membaca tabel `Penjualan` sendiri (aturan #14).
 */
final class RingkasanPenjualanDokumen
{
    /**
     * @param  list<int>  $id
     * @return array<int, array{Uuid: string, Nomor: string, TanggalBisnis: string, TotalAkhir: string, Status: string}>
     */
    public function AmbilBanyak(array $id): array
    {
        $id = array_values(array_unique(array_filter($id, 'is_int')));

        if ($id === []) {
            return [];
        }

        $hasil = [];

        foreach (Penjualan::query()->whereIn('Id', $id)->get(['Id', 'Uuid', 'Nomor', 'TanggalBisnis', 'TotalAkhir', 'Status']) as $p) {
            $hasil[$p->Id] = [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'TotalAkhir' => (string) $p->TotalAkhir,
                'Status' => $p->Status->value,
            ];
        }

        return $hasil;
    }
}
