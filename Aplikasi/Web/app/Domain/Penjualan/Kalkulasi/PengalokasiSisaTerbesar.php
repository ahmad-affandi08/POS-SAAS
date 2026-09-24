<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Alokasi angka dokumen ke baris dengan **metode sisa terbesar** (Hamilton, Rincian F-07a): setiap bagian tepat
 * dibulatkan ke bawah ke sen, sisa sen diberikan satu per satu ke baris dengan pecahan terbuang terbesar (seri: baris
 * lebih awal). Σ hasil selalu sama persis dengan total.
 */
final class PengalokasiSisaTerbesar
{
    /**
     * Membagi total sebanding bobot (misal diskon pesanan sebanding Netto baris). Bobot nol semua = semua nol.
     *
     * @param  list<Uang>  $bobot
     * @return list<Uang>
     */
    public function AlokasikanSebanding(Uang $total, array $bobot): array
    {
        $jumlahBobot = BigRational::zero();

        foreach ($bobot as $satuBobot) {
            if ($satuBobot->BernilaiNegatif()) {
                throw new InvalidArgumentException("Bobot alokasi tidak boleh negatif: {$satuBobot}");
            }

            $jumlahBobot = $jumlahBobot->plus($satuBobot->KeString());
        }

        if ($jumlahBobot->isZero() || $total->BernilaiNol()) {
            return array_map(fn (): Uang => Uang::Nol(), $bobot);
        }

        $nilaiTotal = BigRational::of($total->KeString());

        return $this->AlokasikanTepat($total, array_map(
            fn (Uang $satuBobot): BigRational => $nilaiTotal->multipliedBy($satuBobot->KeString())->dividedBy($jumlahBobot),
            $bobot,
        ));
    }

    /**
     * Membulatkan bagian tepat (pecahan eksak) ke sen sehingga Σ = total. Total harus di antara Σ lantai bagian dan
     * Σ lantai + jumlah baris sen (dipenuhi bila total = bulat(Σ bagian)).
     *
     * @param  list<BigRational>  $tepat
     * @return list<Uang>
     */
    public function AlokasikanTepat(Uang $total, array $tepat): array
    {
        $hasil = [];
        $pecahan = [];
        $jumlahLantai = BigRational::zero();

        foreach ($tepat as $indeks => $bagian) {
            $lantai = $bagian->toScale(Uang::SKALA, RoundingMode::Floor);
            $hasil[$indeks] = Uang::Dari($lantai);
            $pecahan[$indeks] = $bagian->minus($lantai);
            $jumlahLantai = $jumlahLantai->plus($lantai);
        }

        $sisaSen = BigRational::of($total->KeString())->minus($jumlahLantai)->multipliedBy(100)->toBigInteger()->toInt();

        if ($sisaSen < 0 || $sisaSen > count($tepat)) {
            throw new InvalidArgumentException("Total {$total} tidak dapat dialokasikan ke bagian yang diberikan.");
        }

        $urutan = array_keys($tepat);
        usort($urutan, fn (int $a, int $b): int => $pecahan[$b]->compareTo($pecahan[$a]) ?: $a <=> $b);
        $satuSen = Uang::Dari('0.01');

        foreach (array_slice($urutan, 0, $sisaSen) as $indeks) {
            $hasil[$indeks] = $hasil[$indeks]->Tambah($satuSen);
        }

        return array_values($hasil);
    }
}
