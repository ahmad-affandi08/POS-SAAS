<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use Carbon\CarbonImmutable;

/**
 * Data buku stok untuk laporan stok F-14a (domain Laporan tidak membaca tabel Persediaan langsung):
 * - posisi & nilai persediaan per (produk, lokasi stok) pada akhir suatu tanggal bisnis dari ledger `MutasiStok`
 *   (Σ Jumlah, Σ TotalHpp untuk `TanggalBisnis` ≤ tanggal), sama dengan cara `SaldoStok` dibangun ulang;
 * - saldo terkini (`SaldoStok`) untuk pasangan produk × lokasi (stok kritis).
 */
final class StokUntukLaporan
{
    /**
     * @param  list<int>  $idGudang
     * @return list<array{IdProduk: int, IdGudang: int, Jumlah: string, Nilai: string}> pasangan yang jumlah atau nilainya bukan nol
     */
    public function AmbilNilaiPadaTanggal(CarbonImmutable $tanggal, array $idGudang): array
    {
        if ($idGudang === []) {
            return [];
        }

        $baris = MutasiStok::query()
            ->whereIn('IdGudang', $idGudang)
            ->where('TanggalBisnis', '<=', $tanggal->toDateString())
            ->groupBy('IdProduk', 'IdGudang')
            ->selectRaw('`IdProduk`, `IdGudang`, COALESCE(SUM(`Jumlah`), 0) AS `Jumlah`, COALESCE(SUM(`TotalHpp`), 0) AS `Nilai`')
            ->havingRaw('SUM(`Jumlah`) <> 0 OR SUM(`TotalHpp`) <> 0')
            ->orderBy('IdGudang')
            ->orderBy('IdProduk')
            ->toBase()
            ->get();

        return array_values(array_map(fn (object $b): array => [
            'IdProduk' => (int) $b->IdProduk,
            'IdGudang' => (int) $b->IdGudang,
            'Jumlah' => Kuantitas::Dari((string) $b->Jumlah)->KeString(),
            'Nilai' => Uang::Dari((string) $b->Nilai)->KeString(),
        ], $baris->all()));
    }

    /**
     * Saldo terkini per pasangan; pasangan tanpa baris `SaldoStok` tidak ada di hasil (saldo nol).
     *
     * @param  list<int>  $idGudang
     * @param  list<int>  $idProduk
     * @return array<string, array{Jumlah: string, Nilai: string}> kunci = "IdProduk|IdGudang"
     */
    public function AmbilSaldo(array $idGudang, array $idProduk): array
    {
        if ($idGudang === [] || $idProduk === []) {
            return [];
        }

        $hasil = [];

        foreach (array_chunk(array_values(array_unique($idProduk)), 1000) as $potongan) {
            foreach (SaldoStok::query()->whereIn('IdGudang', $idGudang)->whereIn('IdProduk', $potongan)->get(['IdProduk', 'IdGudang', 'JumlahTersedia', 'NilaiPersediaan']) as $s) {
                $hasil["{$s->IdProduk}|{$s->IdGudang}"] = ['Jumlah' => (string) $s->JumlahTersedia, 'Nilai' => (string) $s->NilaiPersediaan];
            }
        }

        return $hasil;
    }
}
