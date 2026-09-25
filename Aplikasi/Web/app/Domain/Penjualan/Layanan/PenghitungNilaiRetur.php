<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Data\DataNilaiReturBaris;
use App\Domain\Penjualan\Data\DataSudahDiretur;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Nilai retur per baris (PRD "Rincian F-09 fase 1"): bagian proporsional snapshot baris penjualan
 * (`TotalBaris`/`JumlahPajak`/`BiayaLayanan` × jumlah retur ÷ jumlah jual, dibulatkan ke sen HalfUp); retur yang
 * menghabiskan sisa baris mengambil sisa nilai sehingga Σ retur = snapshot baris. Aplikasi POS memakai rumus yang
 * sama (`penjualan/cari` mengirim `JumlahBisaDiretur` & `NilaiBisaDiretur`) agar `Ringkasan.TotalRefund` cocok.
 */
final class PenghitungNilaiRetur
{
    /**
     * Akumulasi retur sebelumnya per baris penjualan.
     *
     * @param  list<int>  $idDetail
     * @return array<int, DataSudahDiretur> kunci = IdPenjualanDetail (baris tanpa retur tidak ada)
     */
    public function AmbilSudahDiretur(array $idDetail): array
    {
        if ($idDetail === []) {
            return [];
        }

        $hasil = [];
        $baris = ReturPenjualanDetail::query()
            ->whereIn('IdPenjualanDetail', array_values(array_unique($idDetail)))
            ->groupBy('IdPenjualanDetail')
            ->selectRaw('`IdPenjualanDetail`, SUM(`Jumlah`) AS `Jumlah`, SUM(`NilaiBaris`) AS `Nilai`, SUM(`Pajak`) AS `Pajak`, SUM(`BiayaLayanan`) AS `Layanan`')
            ->toBase()
            ->get();

        foreach ($baris as $b) {
            $hasil[(int) $b->IdPenjualanDetail] = new DataSudahDiretur(
                Kuantitas::Dari(self::Teks($b->Jumlah)),
                Uang::Dari(self::Teks($b->Nilai)),
                Uang::Dari(self::Teks($b->Pajak)),
                Uang::Dari(self::Teks($b->Layanan)),
            );
        }

        return $hasil;
    }

    /** Jumlah yang masih bisa diretur (satuan jual). */
    public function HitungSisa(PenjualanDetail $detail, DataSudahDiretur $sudah): Kuantitas
    {
        return Kuantitas::Dari($detail->Jumlah)->Kurangi($sudah->jumlah);
    }

    /** Nilai yang masih bisa dikembalikan untuk baris ini (sisa `TotalBaris`). */
    public function HitungSisaNilai(PenjualanDetail $detail, DataSudahDiretur $sudah): Uang
    {
        return Uang::Dari($detail->TotalBaris)->Kurangi($sudah->nilai);
    }

    /**
     * Nilai retur `jumlah` (satuan jual, > 0 dan ≤ sisa; pemanggil memeriksa batasnya).
     */
    public function Hitung(PenjualanDetail $detail, DataSudahDiretur $sudah, Kuantitas $jumlah): DataNilaiReturBaris
    {
        $terakhir = $jumlah->SamaDengan($this->HitungSisa($detail, $sudah));
        $jumlahDasar = $jumlah->Kali($detail->KonversiKeDasar);

        if ($terakhir) {
            return new DataNilaiReturBaris(
                $jumlah,
                $jumlahDasar,
                Uang::Dari($detail->TotalBaris)->Kurangi($sudah->nilai),
                Uang::Dari($detail->JumlahPajak)->Kurangi($sudah->pajak),
                Uang::Dari($detail->BiayaLayanan)->Kurangi($sudah->biayaLayanan),
                true,
            );
        }

        return new DataNilaiReturBaris(
            $jumlah,
            $jumlahDasar,
            self::Bagian($detail->TotalBaris, $jumlah, $detail->Jumlah),
            self::Bagian($detail->JumlahPajak, $jumlah, $detail->Jumlah),
            self::Bagian($detail->BiayaLayanan, $jumlah, $detail->Jumlah),
            false,
        );
    }

    /** nilai × jumlah ÷ jumlahJual, dibulatkan ke sen HalfUp. */
    private static function Bagian(string $nilai, Kuantitas $jumlah, string $jumlahJual): Uang
    {
        return Uang::Dari(BigDecimal::of($nilai)->multipliedBy($jumlah->KeDesimal())->dividedBy($jumlahJual, Uang::SKALA, RoundingMode::HalfUp));
    }

    private static function Teks(mixed $nilai): string
    {
        return is_numeric($nilai) ? (string) $nilai : '0';
    }
}
