<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Aritmetika HPP tanpa float (DesainF05a C.3). Semua pembulatan eksplisit:
 * - `Nilai(q, c) = (|q| × c)` skala 2 HalfUp (nilai persediaan, Rupiah).
 * - `Hpp(V, q) = (V ÷ |q|)` skala 6 HalfUp (HPP per satuan dasar).
 */
final class AritmetikaHpp
{
    public const SKALA_HPP = 6;

    /** Nilai persediaan |q| × c, dibulatkan HalfUp ke sen. Selalu ≥ 0 bila c ≥ 0. */
    public static function Nilai(Kuantitas $jumlah, BigDecimal $hppSatuan): Uang
    {
        return Uang::Dari($jumlah->KeDesimal()->abs()->multipliedBy($hppSatuan)->toScale(Uang::SKALA, RoundingMode::HalfUp));
    }

    /** Nilai(q, c) bertanda mengikuti q (q < 0 menghasilkan nilai negatif). */
    public static function NilaiBertanda(Kuantitas $jumlah, BigDecimal $hppSatuan): Uang
    {
        $nilai = self::Nilai($jumlah, $hppSatuan);

        return $jumlah->BernilaiNegatif() ? self::Negasi($nilai) : $nilai;
    }

    /** HPP per satuan V ÷ |q| skala 6 HalfUp. `jumlah` tidak boleh 0. */
    public static function Hpp(Uang $nilai, Kuantitas $jumlah): BigDecimal
    {
        $pembagi = $jumlah->KeDesimal()->abs();

        if ($pembagi->isZero()) {
            throw new InvalidArgumentException('HPP per satuan tidak bisa dihitung dari jumlah 0.');
        }

        return self::KeDesimal($nilai)->dividedBy($pembagi, self::SKALA_HPP, RoundingMode::HalfUp);
    }

    /**
     * Alokasi nilai satu baris ber-nomor seri (DesainF05a C.3 contoh #6): total = Nilai(n, c); n−1 seri pertama
     * mendapat c dibulatkan ke bawah ke sen, seri terakhir mendapat sisanya (selalu ≥ 0). Σ hasil = Nilai(n, c).
     *
     * @return list<Uang>
     */
    public static function AlokasikanNilaiSeri(int $jumlahSeri, BigDecimal $hppSatuan): array
    {
        if ($jumlahSeri < 1) {
            throw new InvalidArgumentException('Jumlah nomor seri minimal 1.');
        }

        $total = self::Nilai(Kuantitas::Dari($jumlahSeri), $hppSatuan);
        $perSeri = Uang::Dari($hppSatuan->toScale(Uang::SKALA, RoundingMode::Down));
        $hasil = [];
        $terpakai = Uang::Nol();

        for ($i = 1; $i < $jumlahSeri; $i++) {
            $hasil[] = $perSeri;
            $terpakai = $terpakai->Tambah($perSeri);
        }

        $hasil[] = $total->Kurangi($terpakai);

        return $hasil;
    }

    public static function KeDesimal(Uang $nilai): BigDecimal
    {
        return BigDecimal::of($nilai->KeString());
    }

    public static function Negasi(Uang $nilai): Uang
    {
        return Uang::Nol()->Kurangi($nilai);
    }

    public static function AmbilMutlak(Uang $nilai): Uang
    {
        return $nilai->BernilaiNegatif() ? self::Negasi($nilai) : $nilai;
    }

    public static function AmbilMinimum(Uang $a, Uang $b): Uang
    {
        return $a->Bandingkan($b) <= 0 ? $a : $b;
    }

    public static function AmbilMaksimum(Uang $a, Uang $b): Uang
    {
        return $a->Bandingkan($b) >= 0 ? $a : $b;
    }

    public static function CekNol(Kuantitas $jumlah): bool
    {
        return $jumlah->KeDesimal()->isZero();
    }

    public static function CekPositif(Kuantitas $jumlah): bool
    {
        return $jumlah->KeDesimal()->isPositive();
    }

    /** Nilai mutlak kuantitas. */
    public static function AmbilMutlakJumlah(Kuantitas $jumlah): Kuantitas
    {
        return $jumlah->BernilaiNegatif() ? $jumlah->Negasi() : $jumlah;
    }

    public static function AmbilMinimumJumlah(Kuantitas $a, Kuantitas $b): Kuantitas
    {
        return $a->Bandingkan($b) <= 0 ? $a : $b;
    }
}
