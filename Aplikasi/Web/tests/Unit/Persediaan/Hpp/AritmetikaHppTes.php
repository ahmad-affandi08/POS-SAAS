<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use Brick\Math\BigDecimal;

describe('F-05a AritmetikaHpp (DesainF05a C.3): pembulatan eksplisit tanpa float', function (): void {
    it('Nilai(q, c) = (|q| × c) dibulatkan HalfUp ke 2 desimal', function (string $jumlah, string $hpp, string $harapan): void {
        expect(AritmetikaHpp::Nilai(Kuantitas::Dari($jumlah), BigDecimal::of($hpp))->KeString())->toBe($harapan);
    })->with([
        'stok awal 10 @ 1234.5678' => ['10', '1234.5678', '12345.68'],
        'jual 3 @ 1234.568 (3703.704)' => ['3', '1234.568000', '3703.70'],
        'jumlah negatif memakai nilai mutlak' => ['-3', '1234.568000', '3703.70'],
        'setengah sen dibulatkan ke atas' => ['1', '0.005', '0.01'],
        'di bawah setengah sen dibulatkan ke bawah' => ['1', '0.004999', '0.00'],
        'kuantitas desimal kg' => ['2.5000', '14250.500000', '35626.25'],
        '12 @ 1066.666667 (12800.000004)' => ['12', '1066.666667', '12800.00'],
        'miliaran rupiah' => ['1500', '7500000.123456', '11250000185.18'],
    ]);

    it('NilaiBertanda mengikuti tanda jumlah', function (): void {
        expect(AritmetikaHpp::NilaiBertanda(Kuantitas::Dari('-3'), BigDecimal::of('1000'))->KeString())->toBe('-3000.00')
            ->and(AritmetikaHpp::NilaiBertanda(Kuantitas::Dari('3'), BigDecimal::of('1000'))->KeString())->toBe('3000.00');
    });

    it('Hpp(V, q) = (V ÷ |q|) dibulatkan HalfUp ke 6 desimal', function (string $nilai, string $jumlah, string $harapan): void {
        expect((string) AritmetikaHpp::Hpp(Uang::Dari($nilai), Kuantitas::Dari($jumlah)))->toBe($harapan);
    })->with([
        'rata-rata stok awal' => ['12345.68', '10', '1234.568000'],
        'rata-rata setelah penerimaan' => ['15141.98', '12', '1261.831667'],
        'sepertiga dibulatkan ke bawah' => ['1000.00', '3', '333.333333'],
        'dua pertiga dibulatkan ke atas' => ['20.00', '3', '6.666667'],
        'HPP keluar FIFO' => ['12400.00', '-12', '1033.333333'],
        'kuantitas desimal' => ['35626.25', '2.5', '14250.500000'],
        'nilai nol' => ['0.00', '7', '0.000000'],
    ]);

    it('Hpp dengan jumlah 0 ditolak (tidak ada pembagian nol diam-diam)', function (): void {
        AritmetikaHpp::Hpp(Uang::Dari('1000'), Kuantitas::Nol());
    })->throws(InvalidArgumentException::class);

    it('contoh #6 alokasi nomor seri: n−1 seri = c dibulatkan ke bawah, seri terakhir = sisa; Σ = Nilai(n, c)', function (int $n, string $hpp, array $harapan): void {
        $hasil = AritmetikaHpp::AlokasikanNilaiSeri($n, BigDecimal::of($hpp));
        $total = array_reduce($hasil, fn (Uang $t, Uang $u): Uang => $t->Tambah($u), Uang::Nol());

        expect(array_map(fn (Uang $u): string => $u->KeString(), $hasil))->toBe($harapan)
            ->and($total->KeString())->toBe(AritmetikaHpp::Nilai(Kuantitas::Dari($n), BigDecimal::of($hpp))->KeString());
    })->with([
        '3 @ 333.333333' => [3, '333.333333', ['333.33', '333.33', '333.34']],
        '1 @ 675000.555555' => [1, '675000.555555', ['675000.56']],
        '4 @ 2499999.999999 (sisa terakhir terbesar)' => [4, '2499999.999999', ['2499999.99', '2499999.99', '2499999.99', '2500000.03']],
        '2 @ 0' => [2, '0', ['0.00', '0.00']],
    ]);
});
