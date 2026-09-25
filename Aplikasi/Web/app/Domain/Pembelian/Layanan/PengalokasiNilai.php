<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Alokasi nilai dokumen pembelian (ongkir, PPN, nilai nomor seri) ke baris sebanding bobot dengan metode sisa
 * terbesar: setiap bagian dibulatkan ke bawah ke sen, sisa sen ke pecahan terbuang terbesar (seri: baris lebih awal).
 * Σ hasil selalu tepat sama dengan total. Bobot nol semua = dibagi rata.
 */
final class PengalokasiNilai
{
    /**
     * @param  list<Uang>  $bobot
     * @return list<Uang>
     */
    public function Alokasikan(Uang $total, array $bobot): array
    {
        if ($bobot === []) {
            return [];
        }

        $jumlahBobot = BigRational::zero();

        foreach ($bobot as $satu) {
            if ($satu->BernilaiNegatif()) {
                throw new InvalidArgumentException("Bobot alokasi tidak boleh negatif: {$satu}");
            }

            $jumlahBobot = $jumlahBobot->plus($satu->KeString());
        }

        if ($jumlahBobot->isZero()) {
            $bobot = array_map(fn (): Uang => Uang::Dari(1), $bobot);
            $jumlahBobot = BigRational::of(count($bobot));
        }

        $nilaiTotal = BigRational::of($total->KeString());
        $hasil = [];
        $pecahan = [];
        $jumlahLantai = BigRational::zero();

        foreach (array_values($bobot) as $indeks => $satu) {
            $bagian = $nilaiTotal->multipliedBy($satu->KeString())->dividedBy($jumlahBobot);
            $lantai = $bagian->toScale(Uang::SKALA, RoundingMode::Floor);
            $hasil[$indeks] = Uang::Dari($lantai);
            $pecahan[$indeks] = $bagian->minus($lantai);
            $jumlahLantai = $jumlahLantai->plus($lantai);
        }

        $sisaSen = $nilaiTotal->minus($jumlahLantai)->multipliedBy(100)->toBigInteger()->toInt();
        $urutan = array_keys($pecahan);
        usort($urutan, fn (int $a, int $b): int => $pecahan[$b]->compareTo($pecahan[$a]) ?: $a <=> $b);
        $satuSen = Uang::Dari('0.01');

        foreach (array_slice($urutan, 0, max(0, $sisaSen)) as $indeks) {
            $hasil[$indeks] = $hasil[$indeks]->Tambah($satuSen);
        }

        return array_values($hasil);
    }
}
