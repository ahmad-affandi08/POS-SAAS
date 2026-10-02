<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Persediaan\Enum\StatusReservasiStok;
use App\Domain\Persediaan\Model\ReservasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Cadangan stok kanal online (F-17 v3.48), API publik Persediaan. Barang yang dipesan lewat toko online dicadangkan di
 * lokasi stok Toko outlet sampai pesanan ditagih kasir (`Pakai`) atau batal/ditolak/kedaluwarsa (`Lepas`), sehingga
 * dua pembeli tidak bisa memesan sisa stok yang sama.
 *
 * Kebutuhan diuraikan seperti penjualan POS (`KomposisiPenjualan`): produk berstok itu sendiri, bahan resep, komponen
 * paket, dan bahan pilihan, dalam satuan dasar. Tersedia untuk kanal online = `SaldoStok.JumlahTersedia − Σ cadangan
 * Aktif` (sumber lain). Kurang hanya ditolak bila produk itu tidak boleh minus (BR-05.2 lewat `PemeriksaStokMinus`):
 * toko yang mengizinkan stok minus tetap menerima pesanan, cadangannya tetap tercatat.
 *
 * Bukan ledger stok: `SaldoStok` dan `MutasiStok` tidak disentuh; penjualan kasir (offline-first) tidak dihalangi
 * cadangan. Outlet tanpa lokasi stok Toko tidak dicadangkan (penjualannya pun belum bisa mengurangi stok).
 */
final class PencadangStok
{
    public const SUMBER_PESANAN_ONLINE = 'PesananOnline';

    public function __construct(
        private readonly KomposisiPenjualan $komposisi,
        private readonly OutletPenjualan $outlet,
        private readonly InfoProdukStok $infoProduk,
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly PemeriksaStokMinus $pemeriksa,
        private readonly PengunciSaldoStok $pengunci,
    ) {}

    /**
     * Pemeriksaan tanpa kunci (hitung keranjang): galat `StokTidakCukup` lebih awal, sebelum pembeli mengisi data.
     *
     * @param  list<array{UuidProduk: string, UuidProdukSatuan: string|null, Jumlah: Kuantitas, UuidPilihan: list<string>}>  $baris
     */
    public function Periksa(int $idOutlet, array $baris): void
    {
        $idGudang = $this->outlet->AmbilIdGudangToko($idOutlet);

        if ($idGudang === null) {
            return;
        }

        $kebutuhan = $this->HitungKebutuhan($baris);

        if ($kebutuhan === []) {
            return;
        }

        $saldo = SaldoStok::query()->where('IdGudang', $idGudang)->whereIn('IdProduk', array_keys($kebutuhan))->pluck('JumlahTersedia', 'IdProduk')->all();
        $this->PastikanCukup($kebutuhan, $saldo, $this->AmbilTercadang($idGudang, array_keys($kebutuhan)));
    }

    /**
     * Mencadangkan di transaksi pemanggil, di bawah kunci `SaldoStok` (urut IdProduk) supaya dua checkout bersamaan
     * tidak sama-sama lolos. Idempoten per sumber: sumber yang sudah punya cadangan tidak dicadangkan ulang.
     *
     * @param  list<array{UuidProduk: string, UuidProdukSatuan: string|null, Jumlah: Kuantitas, UuidPilihan: list<string>}>  $baris
     */
    public function Cadangkan(string $jenisSumber, string $uuidSumber, int $idOutlet, array $baris): void
    {
        $idGudang = $this->outlet->AmbilIdGudangToko($idOutlet);

        if ($idGudang === null || ReservasiStok::query()->where('JenisSumber', $jenisSumber)->where('UuidSumber', $uuidSumber)->exists()) {
            return;
        }

        $kebutuhan = $this->HitungKebutuhan($baris);

        if ($kebutuhan === []) {
            return;
        }

        $terkunci = $this->pengunci->Kunci(array_map(fn (int $id): array => [$id, $idGudang], array_keys($kebutuhan)));
        $saldo = [];

        foreach ($terkunci as $s) {
            $saldo[$s->IdProduk] = $s->JumlahTersedia;
        }

        $this->PastikanCukup($kebutuhan, $saldo, $this->AmbilTercadang($idGudang, array_keys($kebutuhan)));

        foreach ($kebutuhan as $idProduk => $k) {
            ReservasiStok::query()->create([
                'JenisSumber' => $jenisSumber,
                'UuidSumber' => $uuidSumber,
                'IdProduk' => $idProduk,
                'IdGudang' => $idGudang,
                'Jumlah' => $k['Jumlah']->KeString(),
                'Status' => StatusReservasiStok::Aktif,
            ]);
        }
    }

    /** Sumber batal: cadangan aktifnya dilepas. Aman dipanggil berulang atau untuk sumber tanpa cadangan. */
    public function Lepas(string $jenisSumber, string $uuidSumber): int
    {
        return $this->Selesaikan($jenisSumber, $uuidSumber, StatusReservasiStok::Dilepas);
    }

    /** Sumber ditagih: stoknya kini berkurang lewat `MutasiStok` penjualan, jadi cadangannya ditutup. */
    public function Pakai(string $jenisSumber, string $uuidSumber): int
    {
        return $this->Selesaikan($jenisSumber, $uuidSumber, StatusReservasiStok::Dipakai);
    }

    private function Selesaikan(string $jenisSumber, string $uuidSumber, StatusReservasiStok $tujuan): int
    {
        return ReservasiStok::query()
            ->where('JenisSumber', $jenisSumber)
            ->where('UuidSumber', $uuidSumber)
            ->where('Status', StatusReservasiStok::Aktif->value)
            ->update(['Status' => $tujuan->value, 'DiselesaikanPada' => now()]);
    }

    /**
     * Kebutuhan per produk berstok (satuan dasar) beserta nama produk yang dipesan, untuk pesan yang dimengerti pembeli
     * (bahan resep tidak disebut namanya).
     *
     * @param  list<array{UuidProduk: string, UuidProdukSatuan: string|null, Jumlah: Kuantitas, UuidPilihan: list<string>}>  $baris
     * @return array<int, array{Jumlah: Kuantitas, Dipesan: list<string>}>
     */
    private function HitungKebutuhan(array $baris): array
    {
        $produk = $this->komposisi->AmbilProduk(array_values(array_map(fn (array $b): string => $b['UuidProduk'], $baris)));
        $kebutuhan = $this->komposisi->AmbilKebutuhanStok(array_values(array_map(fn ($p): int => $p->id, $produk)));
        $pilihan = $this->komposisi->AmbilPilihan(array_values(array_unique(array_merge([], ...array_map(fn (array $b): array => $b['UuidPilihan'], $baris)))));
        /** @var array<int, array{Jumlah: BigDecimal, Dipesan: array<string, true>}> $total */
        $total = [];

        $tambah = function (int $idProduk, BigDecimal $jumlah, string $nama) use (&$total): void {
            $total[$idProduk] ??= ['Jumlah' => BigDecimal::zero(), 'Dipesan' => []];
            $total[$idProduk]['Jumlah'] = $total[$idProduk]['Jumlah']->plus($jumlah);
            $total[$idProduk]['Dipesan'][$nama] = true;
        };

        foreach ($baris as $b) {
            $p = $produk[$b['UuidProduk']] ?? null;

            if ($p === null || $p->dihapus) {
                continue;
            }

            $konversi = $b['UuidProdukSatuan'] === null ? '1' : ($p->satuan[$b['UuidProdukSatuan']]['KonversiKeDasar'] ?? '1');
            $dasar = $b['Jumlah']->KeDesimal()->multipliedBy($konversi);

            foreach ($kebutuhan[$p->id] ?? [] as $k) {
                if (! $k->dihapus) {
                    $tambah($k->idProduk, $dasar->multipliedBy($k->pembilang)->dividedBy($k->penyebut, Kuantitas::SKALA, RoundingMode::HalfUp), $p->nama);
                }
            }

            foreach ($b['UuidPilihan'] as $uuidPilihan) {
                $bahan = ($pilihan[$uuidPilihan] ?? null)?->bahan;

                if ($bahan !== null && ! $bahan->dihapus) {
                    $tambah($bahan->idProduk, $b['Jumlah']->KeDesimal()->multipliedBy($bahan->pembilang)->dividedBy($bahan->penyebut, Kuantitas::SKALA, RoundingMode::HalfUp), $p->nama);
                }
            }
        }

        $hasil = [];

        foreach ($total as $idProduk => $t) {
            if ($t['Jumlah']->isPositive()) {
                $hasil[$idProduk] = ['Jumlah' => Kuantitas::Dari($t['Jumlah']->toScale(Kuantitas::SKALA, RoundingMode::HalfUp)), 'Dipesan' => array_keys($t['Dipesan'])];
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * @param  list<int>  $idProduk
     * @return array<int, string> Σ cadangan Aktif per produk
     */
    private function AmbilTercadang(int $idGudang, array $idProduk): array
    {
        return ReservasiStok::query()
            ->where('IdGudang', $idGudang)
            ->whereIn('IdProduk', $idProduk)
            ->where('Status', StatusReservasiStok::Aktif->value)
            ->groupBy('IdProduk')
            ->selectRaw('IdProduk, SUM(Jumlah) AS Total')
            ->pluck('Total', 'IdProduk')
            ->map(fn (mixed $v): string => (string) $v)
            ->all();
    }

    /**
     * @param  array<int, array{Jumlah: Kuantitas, Dipesan: list<string>}>  $kebutuhan
     * @param  array<int, string>  $saldo
     * @param  array<int, string>  $tercadang
     */
    private function PastikanCukup(array $kebutuhan, array $saldo, array $tercadang): void
    {
        $info = $this->infoProduk->AmbilBanyak(array_keys($kebutuhan));
        $pengaturan = $this->pengaturan->Ambil();
        $kurang = [];

        foreach ($kebutuhan as $idProduk => $k) {
            $tersedia = Kuantitas::Dari($saldo[$idProduk] ?? '0')->Kurangi(Kuantitas::Dari($tercadang[$idProduk] ?? '0'));

            if (isset($info[$idProduk]) && $this->pemeriksa->CekTidakCukup($info[$idProduk], $pengaturan, $tersedia, $k['Jumlah'])) {
                foreach ($k['Dipesan'] as $nama) {
                    $kurang[$nama] = true;
                }
            }
        }

        if ($kurang !== []) {
            throw new PelanggaranAturanBisnis(
                'StokTidakCukup',
                'Stok belum cukup untuk '.implode(', ', array_keys($kurang)).'. Kurangi jumlahnya atau pilih menu lain.',
                'Baris',
            );
        }
    }
}
