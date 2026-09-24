<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\NomorSeri;
use App\Domain\Persediaan\Model\SaldoStok;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rincian batch dan jumlah nomor seri tersedia per (produk, lokasi stok) untuk halaman saldo (DesainF05a C.4, C.8).
 * Kunci hasil = `SaldoStok::BuatKunciPasangan()`. Pasangan tanpa batch bersisa / seri tersedia tidak muncul di hasil.
 */
final class RincianBatchSaldo
{
    /**
     * Batch bersisa (> 0) per pasangan, urut kedaluwarsa terdekat (tanpa kedaluwarsa di akhir) lalu nomor batch.
     *
     * @param  list<array{int, int}>  $pasangan  (IdProduk, IdGudang)
     * @return array<string, list<array{NomorBatch: string, TanggalKedaluwarsa: string|null, JumlahSisa: string}>>
     */
    public function UntukPasangan(array $pasangan): array
    {
        if ($pasangan === []) {
            return [];
        }

        $kueri = BatchStok::query()->where('JumlahSisa', '>', 0);
        $baris = self::SaringPasangan($kueri, $pasangan)
            ->orderByRaw('`TanggalKedaluwarsa` IS NULL')
            ->orderBy('TanggalKedaluwarsa')
            ->orderBy('NomorBatch')
            ->orderBy('Id')
            ->get(['Id', 'IdProduk', 'IdGudang', 'NomorBatch', 'TanggalKedaluwarsa', 'JumlahSisa']);

        $hasil = [];

        foreach ($baris as $batch) {
            $hasil[SaldoStok::BuatKunciPasangan((int) $batch->IdProduk, (int) $batch->IdGudang)][] = [
                'NomorBatch' => $batch->NomorBatch,
                'TanggalKedaluwarsa' => $batch->TanggalKedaluwarsa?->toDateString(),
                'JumlahSisa' => $batch->JumlahSisa,
            ];
        }

        return $hasil;
    }

    /**
     * Banyak nomor seri berstatus `Tersedia` per pasangan (produk, lokasi stok).
     *
     * @param  list<array{int, int}>  $pasangan  (IdProduk, IdGudang)
     * @return array<string, int>
     */
    public function HitungSeriTersedia(array $pasangan): array
    {
        if ($pasangan === []) {
            return [];
        }

        $kueri = NomorSeri::query()->where('Status', StatusNomorSeri::Tersedia->value);
        $baris = self::SaringPasangan($kueri, $pasangan)
            ->groupBy('IdProduk', 'IdGudang')
            ->selectRaw('`IdProduk`, `IdGudang`, COUNT(*) AS `Jumlah`')
            ->toBase()
            ->get();

        $hasil = [];

        foreach ($baris as $satu) {
            $hasil[SaldoStok::BuatKunciPasangan((int) $satu->IdProduk, (int) $satu->IdGudang)] = (int) $satu->Jumlah;
        }

        return $hasil;
    }

    /**
     * Saring (IdProduk, IdGudang) per produk: `(IdProduk = ? AND IdGudang IN (…)) OR …`.
     *
     * @template TModel of BatchStok|NomorSeri
     *
     * @param  Builder<TModel>  $kueri
     * @param  list<array{int, int}>  $pasangan
     * @return Builder<TModel>
     */
    private static function SaringPasangan(Builder $kueri, array $pasangan): Builder
    {
        $perProduk = [];

        foreach ($pasangan as [$idProduk, $idGudang]) {
            $perProduk[$idProduk][$idGudang] = $idGudang;
        }

        return $kueri->where(function (Builder $atau) use ($perProduk): void {
            foreach ($perProduk as $idProduk => $daftarGudang) {
                $atau->orWhere(fn (Builder $satu): Builder => $satu->where('IdProduk', $idProduk)->whereIn('IdGudang', array_values($daftarGudang)));
            }
        });
    }
}
