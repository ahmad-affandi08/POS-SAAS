<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Data\DataLaporanShift;
use App\Domain\Kasir\Enum\JenisMutasiKas;
use App\Domain\Kasir\Model\Shift;
use App\Domain\Penjualan\Kueri\RingkasanPenjualanShift;

/**
 * Laporan shift X (berjalan) / Z (setelah tutup) dari data server (F-11): kas awal, mutasi kas, dan ringkasan
 * penjualan (kueri publik Penjualan). Dipakai `TutupShift` untuk menghitung ulang kas seharusnya dan halaman detail
 * shift back-office.
 */
final class LaporanShift
{
    public function __construct(private readonly RingkasanPenjualanShift $penjualan) {}

    public function Hitung(Shift $shift): DataLaporanShift
    {
        $total = DaftarShift::AmbilTotalMutasi([$shift->Id])[$shift->Id] ?? [];
        $masuk = Uang::Dari($total[JenisMutasiKas::Masuk->value] ?? '0');
        $keluar = Uang::Dari($total[JenisMutasiKas::Keluar->value] ?? '0');
        $setoran = Uang::Dari($total[JenisMutasiKas::Setoran->value] ?? '0');
        $penjualan = $this->penjualan->Ambil($shift->Id);
        $kasAwal = Uang::Dari($shift->KasAwal);

        return new DataLaporanShift(
            kasAwal: $kasAwal,
            totalMasuk: $masuk,
            totalKeluar: $keluar,
            totalSetoran: $setoran,
            penjualan: $penjualan,
            kasSeharusnya: $kasAwal
                ->Tambah($penjualan->tunaiMasukBersih)
                ->Tambah($masuk)
                ->Kurangi($keluar)
                ->Kurangi($setoran)
                ->Kurangi($penjualan->refundTunai),
        );
    }
}
