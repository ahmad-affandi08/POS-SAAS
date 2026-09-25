<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Pelanggan\Enum\JenisMutasiPoin;
use App\Domain\Pelanggan\Enum\SumberMutasiPoin;
use App\Domain\Pelanggan\Model\MutasiPoin;
use Carbon\CarbonImmutable;

/**
 * Buku poin pelanggan (F-16b). Dipanggil di dalam transaksi DB pemanggil.
 * - `Tambah`: baris positif dengan `Sisa` = poin (lot FIFO).
 * - `Kurangi`: baris negatif; `Sisa` lot dikurangi FIFO (lot dokumen asal lebih dulu, lalu yang paling cepat
 *   kedaluwarsa). Lot yang tidak cukup tetap dicatat (pembalikan boleh membuat saldo minus bila poinnya sudah dipakai).
 * - Idempoten per (Jenis, JenisSumber, IdSumber): pemanggilan ulang mengembalikan baris yang sudah ada.
 */
final class BukuPoin
{
    public function Tambah(
        int $idPelanggan,
        int $poin,
        JenisMutasiPoin $jenis,
        SumberMutasiPoin $sumber,
        ?int $idSumber,
        ?CarbonImmutable $kedaluwarsaPada,
        ?string $keterangan = null,
        ?int $idPengguna = null,
    ): MutasiPoin {
        $ada = $this->CariAda($jenis, $sumber, $idSumber);

        if ($ada !== null) {
            return $ada;
        }

        return MutasiPoin::query()->create([
            'IdPelanggan' => $idPelanggan,
            'Jenis' => $jenis,
            'Poin' => $poin,
            'Sisa' => $poin,
            'JenisSumber' => $sumber,
            'IdSumber' => $idSumber,
            'KedaluwarsaPada' => $kedaluwarsaPada?->toDateString(),
            'Keterangan' => $keterangan,
            'IdPengguna' => $idPengguna,
        ]);
    }

    public function Kurangi(
        int $idPelanggan,
        int $poin,
        JenisMutasiPoin $jenis,
        SumberMutasiPoin $sumber,
        ?int $idSumber,
        ?int $idSumberAsal = null,
        ?string $keterangan = null,
        ?int $idPengguna = null,
    ): MutasiPoin {
        $ada = $this->CariAda($jenis, $sumber, $idSumber);

        if ($ada !== null) {
            return $ada;
        }

        $lot = MutasiPoin::query()
            ->where('IdPelanggan', $idPelanggan)
            ->where('Sisa', '>', 0)
            ->orderByRaw('CASE WHEN JenisSumber = ? AND IdSumber = ? THEN 0 ELSE 1 END', [SumberMutasiPoin::Penjualan->value, $idSumberAsal ?? 0])
            ->orderByRaw('KedaluwarsaPada IS NULL')
            ->orderBy('KedaluwarsaPada')
            ->orderBy('Id')
            ->lockForUpdate()
            ->get();
        $kurang = $poin;

        foreach ($lot as $l) {
            if ($kurang === 0) {
                break;
            }

            $ambil = min($kurang, (int) $l->Sisa);
            $l->Sisa = (int) $l->Sisa - $ambil;
            $l->save();
            $kurang -= $ambil;
        }

        return MutasiPoin::query()->create([
            'IdPelanggan' => $idPelanggan,
            'Jenis' => $jenis,
            'Poin' => -$poin,
            'Sisa' => null,
            'JenisSumber' => $sumber,
            'IdSumber' => $idSumber,
            'IdSumberAsal' => $idSumberAsal,
            'Keterangan' => $keterangan,
            'IdPengguna' => $idPengguna,
        ]);
    }

    public function AmbilSaldo(int $idPelanggan): int
    {
        return (int) MutasiPoin::query()->where('IdPelanggan', $idPelanggan)->sum('Poin');
    }

    /**
     * @param  list<int>  $idPelanggan
     * @return array<int, int>
     */
    public function AmbilSaldoBanyak(array $idPelanggan): array
    {
        if ($idPelanggan === []) {
            return [];
        }

        $hasil = [];

        foreach (MutasiPoin::query()->whereIn('IdPelanggan', $idPelanggan)->groupBy('IdPelanggan')->selectRaw('IdPelanggan, SUM(Poin) AS Saldo')->get() as $b) {
            $hasil[(int) $b->getAttribute('IdPelanggan')] = (int) $b->getAttribute('Saldo');
        }

        return $hasil;
    }

    private function CariAda(JenisMutasiPoin $jenis, SumberMutasiPoin $sumber, ?int $idSumber): ?MutasiPoin
    {
        return $idSumber === null ? null : MutasiPoin::query()
            ->where('Jenis', $jenis->value)
            ->where('JenisSumber', $sumber->value)
            ->where('IdSumber', $idSumber)
            ->first();
    }
}
