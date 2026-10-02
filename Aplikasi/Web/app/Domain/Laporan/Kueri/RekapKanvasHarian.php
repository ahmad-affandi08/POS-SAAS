<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Kueri\SetoranShiftHarian;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use App\Domain\Persediaan\Kueri\RekapMutasiHarianGudang;
use Carbon\CarbonImmutable;

/**
 * Modul Salesman bagian 3 (§9.7, kanvas): rekap harian satu kendaraan kanvas (outlet `Kanvas`) pada satu tanggal
 * bisnis, disusun hanya dari Kueri publik domain pemilik datanya (CLAUDE.md #14):
 * - barang per produk dari buku stok lokasi Toko kendaraan (`RekapMutasiHarianGudang`): awal, muat (transfer masuk),
 *   terjual (bersih void), retur pelanggan, bongkar (transfer keluar), lainnya, sisa akhir;
 * - uang dari `AgregatPenjualan` (aturan laporan F-14a: penjualan void keluar dari tanggal jualnya): penjualan tunai
 *   (diterima bersih kembalian), penjualan tempo (piutang baru), metode lain, refund tunai retur, nilai retur;
 * - setoran dari tutup shift F-11 (`SetoranShiftHarian`): kas seharusnya, kas dihitung, selisih.
 */
final class RekapKanvasHarian
{
    public function __construct(
        private readonly RekapMutasiHarianGudang $mutasi,
        private readonly AgregatPenjualan $agregat,
        private readonly SetoranShiftHarian $setoran,
        private readonly InfoProdukStok $infoProduk,
    ) {}

    /**
     * @return array{Tanggal: string, Uang: array{PenjualanTunai: string, PenjualanTempo: string, PenjualanLain: string, RefundTunai: string, NilaiRetur: string, Bersih: string, JumlahTransaksi: int, JumlahVoid: int, JumlahRetur: int}, Setoran: array{JumlahShiftTertutup: int, JumlahShiftBelumDitutup: int, KasAwal: string, KasSeharusnya: string, KasAktual: string, Selisih: string}, Produk: list<array{UuidProduk: string, NamaProduk: string, Sku: string|null, Satuan: string, Awal: string, Muat: string, Terjual: string, Retur: string, Bongkar: string, Lain: string, Sisa: string}>}
     */
    public function Ambil(int $idOutlet, ?int $idGudang, CarbonImmutable $tanggal): array
    {
        return [
            'Tanggal' => $tanggal->toDateString(),
            'Uang' => $this->AmbilUang($idOutlet, $tanggal),
            'Setoran' => $this->setoran->Ambil($tanggal, [$idOutlet])[$idOutlet] ?? [
                'JumlahShiftTertutup' => 0,
                'JumlahShiftBelumDitutup' => 0,
                'KasAwal' => '0.00',
                'KasSeharusnya' => '0.00',
                'KasAktual' => '0.00',
                'Selisih' => '0.00',
            ],
            'Produk' => $idGudang === null ? [] : $this->AmbilProduk($idGudang, $tanggal),
        ];
    }

    /**
     * @return array{PenjualanTunai: string, PenjualanTempo: string, PenjualanLain: string, RefundTunai: string, NilaiRetur: string, Bersih: string, JumlahTransaksi: int, JumlahVoid: int, JumlahRetur: int}
     */
    private function AmbilUang(int $idOutlet, CarbonImmutable $tanggal): array
    {
        $saring = new DataSaringLaporanPenjualan($tanggal, $tanggal, [$idOutlet]);
        $harian = $this->agregat->Harian($saring)[0] ?? null;
        $tunai = Uang::Nol();
        $tempo = Uang::Nol();
        $lain = Uang::Nol();
        $refundTunai = Uang::Nol();

        foreach ($this->agregat->PerMetode($saring) as $metode) {
            $diterima = Uang::Dari($metode['Diterima']);

            if ($metode['Jenis'] === JenisMetodePembayaran::Tunai->value) {
                $tunai = $tunai->Tambah($diterima);
                $refundTunai = $refundTunai->Tambah(Uang::Dari($metode['Refund']));
            } elseif ($metode['Jenis'] === JenisMetodePembayaran::Tempo->value) {
                $tempo = $tempo->Tambah($diterima);
            } else {
                $lain = $lain->Tambah($diterima);
            }
        }

        $agregat = $harian['Agregat'] ?? $this->agregat->Total($saring);

        return [
            'PenjualanTunai' => $tunai->KeString(),
            'PenjualanTempo' => $tempo->KeString(),
            'PenjualanLain' => $lain->KeString(),
            'RefundTunai' => $refundTunai->KeString(),
            'NilaiRetur' => $agregat->retur->KeString(),
            'Bersih' => $agregat->Bersih()->KeString(),
            'JumlahTransaksi' => $agregat->jumlahTransaksi,
            'JumlahVoid' => $harian['JumlahVoid'] ?? 0,
            'JumlahRetur' => $agregat->jumlahRetur,
        ];
    }

    /**
     * @return list<array{UuidProduk: string, NamaProduk: string, Sku: string|null, Satuan: string, Awal: string, Muat: string, Terjual: string, Retur: string, Bongkar: string, Lain: string, Sisa: string}>
     */
    private function AmbilProduk(int $idGudang, CarbonImmutable $tanggal): array
    {
        $baris = $this->mutasi->Ambil($idGudang, $tanggal);
        $produk = $this->infoProduk->AmbilBanyak(array_map(fn (array $b): int => $b['IdProduk'], $baris), denganTerhapus: true);
        $hasil = [];

        foreach ($baris as $b) {
            $info = $produk[$b['IdProduk']] ?? null;

            if ($info === null) {
                continue;
            }

            $hasil[] = [
                'UuidProduk' => $info->uuid,
                'NamaProduk' => $info->nama,
                'Sku' => $info->sku,
                'Satuan' => $info->simbolSatuan,
                'Awal' => $b['Awal'],
                'Muat' => $b['Muat'],
                'Terjual' => $b['Terjual'],
                'Retur' => $b['Retur'],
                'Bongkar' => $b['Bongkar'],
                'Lain' => $b['Lain'],
                'Sisa' => $b['Sisa'],
            ];
        }

        usort($hasil, fn (array $a, array $b): int => strcasecmp($a['NamaProduk'], $b['NamaProduk']));

        return $hasil;
    }
}
