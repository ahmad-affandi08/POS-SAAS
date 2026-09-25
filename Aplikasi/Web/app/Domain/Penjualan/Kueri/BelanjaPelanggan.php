<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan & riwayat belanja pelanggan (F-16a, dipakai domain Pelanggan). Penjualan `Void` tidak dihitung; total
 * belanja = Σ `TotalAkhir` sebelum retur.
 */
final class BelanjaPelanggan
{
    /**
     * @param  list<int>  $idPelanggan
     * @return array<int, array{JumlahTransaksi: int, TotalBelanja: string, TerakhirPada: string|null}>
     */
    public function AmbilRingkasan(array $idPelanggan): array
    {
        if ($idPelanggan === []) {
            return [];
        }

        $baris = Penjualan::query()
            ->whereIn('IdPelanggan', $idPelanggan)
            ->where('Status', '!=', StatusPenjualan::Void->value)
            ->groupBy('IdPelanggan')
            ->get([
                'IdPelanggan',
                DB::raw('COUNT(*) AS JumlahTransaksi'),
                DB::raw('SUM(TotalAkhir) AS TotalBelanja'),
                DB::raw('MAX(DibuatOfflinePada) AS TerakhirPada'),
            ]);

        $hasil = [];

        foreach ($baris as $b) {
            /** @var object{IdPelanggan: int|string, JumlahTransaksi: int|string, TotalBelanja: string|null, TerakhirPada: string|null} $b */
            $hasil[(int) $b->IdPelanggan] = [
                'JumlahTransaksi' => (int) $b->JumlahTransaksi,
                'TotalBelanja' => (string) ($b->TotalBelanja ?? '0.00'),
                'TerakhirPada' => $b->TerakhirPada === null ? null : (string) $b->TerakhirPada,
            ];
        }

        return $hasil;
    }

    /**
     * Total belanja (tanpa void) per pelanggan sejak [dari] (tanggal bisnis, inklusif), untuk evaluasi tier F-16b.
     *
     * @return array<int, string> IdPelanggan → total (string desimal)
     */
    public function AmbilTotalSejak(string $dari): array
    {
        $hasil = [];

        foreach (Penjualan::query()
            ->whereNotNull('IdPelanggan')
            ->where('TanggalBisnis', '>=', $dari)
            ->where('Status', '!=', StatusPenjualan::Void->value)
            ->groupBy('IdPelanggan')
            ->get(['IdPelanggan', DB::raw('SUM(TotalAkhir) AS Total')]) as $b) {
            $hasil[(int) $b->getAttribute('IdPelanggan')] = (string) $b->getAttribute('Total');
        }

        return $hasil;
    }

    /**
     * Penjualan terbaru pelanggan (semua status), terbaru dulu.
     *
     * @return list<array{Uuid: string, Nomor: string, TanggalBisnis: string, DibuatPada: string|null, Status: string, TotalAkhir: string}>
     */
    public function AmbilRiwayat(int $idPelanggan, int $batas = 50): array
    {
        return array_values(Penjualan::query()
            ->where('IdPelanggan', $idPelanggan)
            ->orderByDesc('DibuatOfflinePada')
            ->orderByDesc('Id')
            ->limit($batas)
            ->get(['Uuid', 'Nomor', 'TanggalBisnis', 'DibuatOfflinePada', 'Status', 'TotalAkhir'])
            ->map(fn (Penjualan $p): array => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'DibuatPada' => $p->DibuatOfflinePada->toIso8601ZuluString(),
                'Status' => $p->Status->value,
                'TotalAkhir' => (string) $p->TotalAkhir,
            ])
            ->all());
    }
}
