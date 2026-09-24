<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Layanan\Hpp\HppFifo;
use App\Domain\Persediaan\Layanan\Hpp\HppRataRataBergerak;
use App\Domain\Persediaan\Layanan\Hpp\KeadaanHpp;
use App\Domain\Persediaan\Layanan\Hpp\MasukanHpp;
use App\Domain\Persediaan\Layanan\Hpp\StrategiHpp;
use Brick\Math\BigDecimal;
use Tests\Pendukung\Persediaan\BantuanBuku;

/*
 * Properti HPP atas urutan acak ber-seed (DesainF05a F): 500 langkah campuran masuk/keluar, berjalan/ditentukan,
 * kuantitas desimal, stok minus, nilai jutaan rupiah. Hanya Uang/Kuantitas/BigDecimal (tanpa float).
 */

/** Kuantitas acak 0,0001–25 (sesekali bilangan bulat), bertanda. */
function BuatJumlahAcakProperti(bool $keluar): Kuantitas
{
    $satuan = mt_rand(0, 2) === 0
        ? BigDecimal::ofUnscaledValue(mt_rand(1, 250000), 4)
        : BigDecimal::of(mt_rand(1, 12));

    return Kuantitas::Dari($keluar ? $satuan->negated() : $satuan);
}

function BuatNilaiAcakProperti(): Uang
{
    return Uang::Dari(BigDecimal::ofUnscaledValue(mt_rand(0, 250_000_000), 2));
}

function JalankanPropertiHppUji(StrategiHpp $strategi, int $benih, bool $fifo): void
{
    mt_srand($benih);
    $keadaan = KeadaanHpp::BuatKosong();
    $totalHpp = Uang::Nol();
    $totalJumlah = Kuantitas::Nol();

    for ($langkah = 1; $langkah <= 500; $langkah++) {
        $keluar = mt_rand(0, 99) < 48;
        $jumlah = BuatJumlahAcakProperti($keluar);
        $ditentukan = $keluar ? mt_rand(0, 4) === 0 : mt_rand(0, 5) !== 0;
        $masukan = new MasukanHpp(
            $jumlah,
            $ditentukan ? ModeNilaiMutasi::Ditentukan : ModeNilaiMutasi::Berjalan,
            $ditentukan ? BuatNilaiAcakProperti() : null,
            kunciBaris: 'L/'.$langkah,
        );

        $hasil = $strategi->Terapkan($keadaan, $masukan);
        $totalHpp = $totalHpp->Tambah($hasil->totalHpp);
        $totalJumlah = $totalJumlah->Tambah($jumlah);
        $pesan = "benih {$benih} langkah {$langkah}";
        $q = $keadaan->jumlah->KeDesimal();
        $n = BigDecimal::of($keadaan->nilai->KeString());

        expect($keadaan->jumlah->SamaDengan($totalJumlah))->toBeTrue($pesan.': Q = Σ q')
            ->and($keadaan->nilai->SamaDengan($totalHpp))->toBeTrue($pesan.': N = Σ TotalHpp')
            ->and($hasil->selisihHpp->SamaDengan($hasil->totalHpp->Kurangi($hasil->nilaiDiminta)))->toBeTrue($pesan.': Selisih')
            ->and(! $q->isZero() || $n->isZero())->toBeTrue($pesan.': Q = 0 ⇒ N = 0')
            ->and(! $q->isPositive() || ! $n->isNegative())->toBeTrue($pesan.': Q > 0 ⇒ N ≥ 0')
            ->and($hasil->hppSatuan->isNegative())->toBeFalse($pesan.': HppSatuan ≥ 0');

        if ($keluar && $masukan->mode === ModeNilaiMutasi::Berjalan) {
            expect($hasil->selisihHpp->BernilaiNol())->toBeTrue($pesan.': keluar berjalan tanpa selisih');
        }

        if ($fifo) {
            foreach ($keadaan->lapisan as $lapisan) {
                expect($lapisan->jumlahSisa->BernilaiNegatif() || $lapisan->nilaiSisa->BernilaiNegatif())->toBeFalse($pesan.': lapisan tidak negatif');
            }

            if (! $q->isNegative()) {
                expect(BantuanBuku::HitungSisaLapisan($keadaan)->SamaDengan($keadaan->jumlah))->toBeTrue($pesan.': Σ JumlahSisa = Q')
                    ->and(BantuanBuku::HitungNilaiLapisan($keadaan)->SamaDengan($keadaan->nilai))->toBeTrue($pesan.': Σ NilaiSisa = N');
            }

            if (! $q->isPositive()) {
                expect($keadaan->AmbilLapisanTerbuka())->toBe([], $pesan.': Q ≤ 0 tanpa lapisan terbuka');
            }
        }
    }
}

describe('F-05a properti HPP (DesainF05a C.3/F): Q=0⇒N=0, Q>0⇒N≥0, N=Σ TotalHpp', function (): void {
    it('rata-rata bergerak atas 500 langkah acak', function (int $benih): void {
        JalankanPropertiHppUji(new HppRataRataBergerak, $benih, false);
    })->with([20260924, 1305, 777]);

    it('FIFO atas 500 langkah acak: lapisan terbuka = saldo saat Q ≥ 0, kosong saat Q ≤ 0', function (int $benih): void {
        JalankanPropertiHppUji(new HppFifo, $benih, true);
    })->with([20260924, 1305, 777]);
});
