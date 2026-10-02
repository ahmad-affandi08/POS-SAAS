<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Katalog\Enum\GolonganObat;
use App\Domain\Katalog\Kueri\ObatUntukLaporan;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Kueri\MutasiObatUntukLaporan;
use Carbon\CarbonImmutable;

/**
 * Data pendukung pelaporan SIPNAP (Sektor Apotek bagian 1, PRD §9.5: "Kepatuhan laporan ke regulator (SIPNAP) di luar
 * lingkup v1; sistem menyediakan export data pendukung"). Per bulan per produk psikotropika & narkotika (UU 35/2009,
 * PMK 3/2015): stok awal, pemasukan dari pemasok, pemasukan lain, pengeluaran penjualan (resep), pengeluaran lain, stok
 * akhir, dihitung dari ledger `MutasiStok` (lewat kueri publik Persediaan) atas lokasi stok outlet yang boleh diakses.
 * Ini **bukan** laporan resmi; angka diisikan sendiri oleh apoteker ke aplikasi SIPNAP.
 *
 * Produk tanpa saldo & tanpa mutasi di bulan itu tidak ditampilkan.
 */
final class LaporanApotek
{
    public function __construct(
        private readonly ObatUntukLaporan $obat,
        private readonly InfoGudang $gudang,
        private readonly MutasiObatUntukLaporan $mutasi,
    ) {}

    /**
     * @param  list<int>|null  $idOutlet  null = semua lokasi stok (termasuk tanpa outlet)
     * @return list<array{UuidProduk: string, NamaProduk: string, Sku: string|null, Golongan: string, LabelGolongan: string, Prekursor: bool, SimbolSatuan: string, StokAwal: string, PemasukanPemasok: string, PemasukanLain: string, PengeluaranPenjualan: string, PengeluaranLain: string, StokAkhir: string}>
     */
    public function DataSipnap(CarbonImmutable $bulan, ?array $idOutlet): array
    {
        $produk = $this->obat->AmbilPerGolongan(array_values(array_filter(GolonganObat::cases(), fn (GolonganObat $g): bool => $g->CekDilaporkanSipnap())));
        $idGudang = array_map(fn ($g): int => $g->id, $this->gudang->AmbilBoleh($idOutlet, false));
        $rekap = $this->mutasi->RekapBulanan(array_column($produk, 'Id'), $idGudang, $bulan->startOfMonth(), $bulan->endOfMonth());
        $hasil = [];

        foreach ($produk as $p) {
            $r = $rekap[$p['Id']];
            $kosong = array_filter($r, fn (string $n): bool => preg_match('/^-?0+(\.0+)?$/', $n) !== 1) === [];

            if ($kosong) {
                continue;
            }

            $hasil[] = [
                'UuidProduk' => $p['Uuid'],
                'NamaProduk' => $p['Nama'],
                'Sku' => $p['Sku'],
                'Golongan' => $p['Golongan']->value,
                'LabelGolongan' => $p['Golongan']->AmbilLabel(),
                'Prekursor' => $p['Prekursor'],
                'SimbolSatuan' => $p['SimbolSatuan'],
                ...$r,
            ];
        }

        return $hasil;
    }
}
