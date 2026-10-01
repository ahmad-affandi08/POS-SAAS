<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use Carbon\CarbonImmutable;

/**
 * F-05i: ringkasan buku stok produk konsinyasi untuk domain Pembelian (hutang & setoran per penitip), tanpa Pembelian
 * menyentuh tabel stok. Per produk: titipan masuk, retur ke penitip, **terjual bersih** (semua mutasi selain
 * `KonsinyasiMasuk`/`KonsinyasiRetur` — penjualan, retur penjualan, dan pembalik void — dibalik tandanya), nilai terjual
 * (= −Σ TotalHpp, sama persis dengan kredit Hutang Konsinyasi di jurnal penjualan J-05.7), dan saldo stok terkini.
 * Rentang tanggal bisnis opsional (inklusif) untuk laporan per periode; saldo selalu terkini.
 */
final class RingkasanMutasiKonsinyasi
{
    /**
     * @param  list<int>  $idProduk
     * @return array<int, array{Masuk: Kuantitas, Retur: Kuantitas, Terjual: Kuantitas, NilaiTerjual: Uang, Saldo: Kuantitas}>
     */
    public function Ambil(array $idProduk, ?CarbonImmutable $dari = null, ?CarbonImmutable $sampai = null): array
    {
        $masuk = [];
        $retur = [];
        $terjual = [];
        $nilai = [];
        $saldo = [];

        foreach (array_chunk(array_values(array_unique($idProduk)), 1000) as $potongan) {
            $baris = MutasiStok::query()
                ->whereIn('IdProduk', $potongan)
                ->when($dari !== null, fn ($kueri) => $kueri->where('TanggalBisnis', '>=', $dari?->toDateString()))
                ->when($sampai !== null, fn ($kueri) => $kueri->where('TanggalBisnis', '<=', $sampai?->toDateString()))
                ->groupBy('IdProduk', 'JenisMutasi')
                ->selectRaw('IdProduk, JenisMutasi, SUM(Jumlah) AS Jumlah, SUM(TotalHpp) AS Nilai')
                ->toBase()
                ->get();

            foreach ($baris as $b) {
                $id = (int) $b->IdProduk;
                $jumlah = Kuantitas::Dari((string) $b->Jumlah);

                if ((string) $b->JenisMutasi === JenisMutasi::KonsinyasiMasuk->value) {
                    $masuk[$id] = ($masuk[$id] ?? Kuantitas::Nol())->Tambah($jumlah);
                } elseif ((string) $b->JenisMutasi === JenisMutasi::KonsinyasiRetur->value) {
                    $retur[$id] = ($retur[$id] ?? Kuantitas::Nol())->Kurangi($jumlah);
                } else {
                    $terjual[$id] = ($terjual[$id] ?? Kuantitas::Nol())->Kurangi($jumlah);
                    $nilai[$id] = ($nilai[$id] ?? Uang::Nol())->Kurangi(Uang::Dari((string) $b->Nilai));
                }
            }

            foreach (SaldoStok::query()->whereIn('IdProduk', $potongan)->groupBy('IdProduk')->selectRaw('IdProduk, SUM(JumlahTersedia) AS Jumlah')->toBase()->get() as $s) {
                $saldo[(int) $s->IdProduk] = Kuantitas::Dari((string) $s->Jumlah);
            }
        }

        $hasil = [];

        foreach ($idProduk as $id) {
            $hasil[$id] = [
                'Masuk' => $masuk[$id] ?? Kuantitas::Nol(),
                'Retur' => $retur[$id] ?? Kuantitas::Nol(),
                'Terjual' => $terjual[$id] ?? Kuantitas::Nol(),
                'NilaiTerjual' => $nilai[$id] ?? Uang::Nol(),
                'Saldo' => $saldo[$id] ?? Kuantitas::Nol(),
            ];
        }

        return $hasil;
    }
}
