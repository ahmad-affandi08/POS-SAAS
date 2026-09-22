<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\RoundingMode;

describe('Uang (PRD §8 F-07, CLAUDE.md #7)', function (): void {
    it('menyimpan nilai sebagai string desimal 2 angka', function (): void {
        expect(Uang::Dari(15000)->KeString())->toBe('15000.00')
            ->and(Uang::Dari('18000.5')->KeString())->toBe('18000.50')
            ->and(json_encode(['Total' => Uang::Dari('63500')]))->toBe('{"Total":"63500.00"}');
    });

    it('menolak nilai dengan lebih dari 2 desimal agar tidak ada pembulatan diam-diam', function (): void {
        Uang::Dari('100.005');
    })->throws(InvalidArgumentException::class);

    it('menghitung contoh struk kafe PRD Lampiran D dengan tepat', function (): void {
        $subtotal = Uang::Dari(18000)->Kali(2)->Tambah(Uang::Dari(25000))->Kurangi(Uang::Dari(6000));
        $biayaLayanan = $subtotal->Kali('0.05');
        $pb1 = $subtotal->Tambah($biayaLayanan)->Kali('0.10');
        $sebelumPembulatan = $subtotal->Tambah($biayaLayanan)->Tambah($pb1);
        $totalAkhir = $sebelumPembulatan->BulatkanKeKelipatan(100, RoundingMode::Down);

        expect($subtotal->KeString())->toBe('55000.00')
            ->and($biayaLayanan->KeString())->toBe('2750.00')
            ->and($pb1->KeString())->toBe('5775.00')
            ->and($sebelumPembulatan->KeString())->toBe('63525.00')
            ->and($totalAkhir->KeString())->toBe('63500.00')
            ->and($totalAkhir->Kurangi($sebelumPembulatan)->KeString())->toBe('-25.00');
    });

    it('membulatkan perkalian dengan mode yang disebut eksplisit', function (): void {
        expect(Uang::Dari('10.05')->Kali('0.5')->KeString())->toBe('5.03')
            ->and(Uang::Dari('10.05')->Kali('0.5', RoundingMode::HalfEven)->KeString())->toBe('5.02')
            ->and(Uang::Dari('-10.05')->Kali('0.5')->KeString())->toBe('-5.03');
    });

    it('membulatkan ke kelipatan dengan semua arah (paritas dengan Paket/Inti Dart)', function (): void {
        $nilai = Uang::Dari(63550);

        expect($nilai->BulatkanKeKelipatan(100, RoundingMode::Down)->KeString())->toBe('63500.00')
            ->and($nilai->BulatkanKeKelipatan(100, RoundingMode::Ceiling)->KeString())->toBe('63600.00')
            ->and($nilai->BulatkanKeKelipatan(100, RoundingMode::HalfUp)->KeString())->toBe('63600.00')
            ->and($nilai->BulatkanKeKelipatan(100, RoundingMode::HalfEven)->KeString())->toBe('63600.00')
            ->and(Uang::Dari(63450)->BulatkanKeKelipatan(100, RoundingMode::HalfEven)->KeString())->toBe('63400.00')
            ->and(Uang::Dari(-63525)->BulatkanKeKelipatan(100, RoundingMode::Floor)->KeString())->toBe('-63600.00');
    });

    it('membandingkan dan memeriksa tanda nilai', function (): void {
        expect(Uang::Dari(100)->Bandingkan(Uang::Dari(200)))->toBe(-1)
            ->and(Uang::Dari('100.00')->SamaDengan(Uang::Dari(100)))->toBeTrue()
            ->and(Uang::Nol()->BernilaiNol())->toBeTrue()
            ->and(Uang::Dari(-5)->BernilaiNegatif())->toBeTrue();
    });

    it('memformat Rupiah gaya Indonesia', function (): void {
        expect(Uang::Dari(1250000)->FormatRupiah())->toBe('Rp 1.250.000')
            ->and(Uang::Dari(-6000)->FormatRupiah())->toBe('−Rp 6.000')
            ->and(Uang::Dari('1234.5')->FormatRupiah())->toBe('Rp 1.234,50')
            ->and(Uang::Dari('999.05')->FormatRupiah())->toBe('Rp 999,05')
            ->and(Uang::Nol()->FormatRupiah())->toBe('Rp 0');
    });
});
