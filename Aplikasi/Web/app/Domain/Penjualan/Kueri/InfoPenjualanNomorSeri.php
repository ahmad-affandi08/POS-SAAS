<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;

/**
 * Penjualan yang memuat nomor seri/IMEI tertentu (F-05h, PRD v3.13), untuk halaman riwayat nomor seri: nomor & tanggal
 * penjualan, pembeli, dan garansi. Kueri publik domain Penjualan; Persediaan tidak membaca tabel penjualan sendiri
 * (aturan #14). Kuncinya `NomorSeri.IdPenjualanDetail`, yang dikosongkan lagi saat void/retur mengembalikan unitnya.
 *
 * Garansi dihitung sama dengan struk digital dan struk cetak (v3.06, v3.08): tanggal bisnis penjualan + masa garansi
 * dalam bulan kalender, hari dipangkas ke akhir bulan (`addMonthsNoOverflow`).
 */
final class InfoPenjualanNomorSeri
{
    public function __construct(private readonly IdentitasPelanggan $pelanggan) {}

    /**
     * @param  list<int>  $idPenjualanDetail
     * @return array<int, array{UuidPenjualan: string, NomorPenjualan: string, TanggalJual: string, UuidPelanggan: string|null, NamaPelanggan: string|null, MasaGaransiBulan: int|null, GaransiSampai: string|null}> kunci = Id baris penjualan
     */
    public function AmbilBanyak(array $idPenjualanDetail): array
    {
        $idPenjualanDetail = array_values(array_unique(array_filter($idPenjualanDetail, 'is_int')));

        if ($idPenjualanDetail === []) {
            return [];
        }

        $baris = PenjualanDetail::query()->whereIn('Id', $idPenjualanDetail)->get(['Id', 'IdPenjualan', 'MasaGaransiBulan']);
        $penjualan = Penjualan::query()->whereIn('Id', $baris->pluck('IdPenjualan')->unique()->all())->get()->keyBy('Id');
        $pelanggan = $this->pelanggan->AmbilNamaBanyak(array_values(array_filter($penjualan->pluck('IdPelanggan')->all(), 'is_int')));
        $hasil = [];

        foreach ($baris as $b) {
            $p = $penjualan->get($b->IdPenjualan);

            if ($p === null) {
                continue;
            }

            $masa = $b->MasaGaransiBulan;
            $hasil[$b->Id] = [
                'UuidPenjualan' => $p->Uuid,
                'NomorPenjualan' => $p->Nomor,
                'TanggalJual' => $p->TanggalBisnis->toDateString(),
                'UuidPelanggan' => $p->IdPelanggan === null ? null : ($pelanggan[$p->IdPelanggan]['Uuid'] ?? null),
                'NamaPelanggan' => $p->IdPelanggan === null ? null : ($pelanggan[$p->IdPelanggan]['Nama'] ?? null),
                'MasaGaransiBulan' => $masa,
                'GaransiSampai' => $masa === null ? null : $p->TanggalBisnis->copy()->addMonthsNoOverflow($masa)->toDateString(),
            ];
        }

        return $hasil;
    }
}
