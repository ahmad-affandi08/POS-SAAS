<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Data\DataBarisDokumenStok;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use App\Domain\Persediaan\Model\SaldoStok;
use Brick\Math\BigDecimal;

/**
 * Taksiran nilai penyesuaian stok untuk batas persetujuan F-05b (`BatasPersetujuanPenyesuaian`, §19.2):
 * Σ |nilai| baris, masuk = jumlah × harga modal yang diisi, keluar = jumlah × HPP rata-rata lokasi saat ini (0 bila
 * belum ada HPP). Dipanggil lagi di dalam transaksi pengajuan setelah SaldoStok dikunci, jadi taksiran itulah yang
 * menentukan perlu tidaknya persetujuan.
 */
final class PenaksirNilaiPenyesuaian
{
    /**
     * @param  list<DataBarisDokumenStok>  $baris
     */
    public function Taksir(array $baris, int $idGudang): Uang
    {
        $hpp = SaldoStok::query()
            ->where('IdGudang', $idGudang)
            ->whereIn('IdProduk', array_values(array_unique(array_map(fn (DataBarisDokumenStok $b): int => $b->idProduk, $baris))))
            ->pluck('HppRataRata', 'IdProduk')
            ->all();
        $total = Uang::Nol();

        foreach ($baris as $b) {
            $mutlak = AritmetikaHpp::AmbilMutlakJumlah($b->jumlah);
            $satuan = $b->jumlah->BernilaiNegatif() ? BigDecimal::of((string) ($hpp[$b->idProduk] ?? '0')) : ($b->hppSatuan ?? BigDecimal::zero());
            $total = $total->Tambah(AritmetikaHpp::Nilai($mutlak, $satuan));
        }

        return $total;
    }
}
