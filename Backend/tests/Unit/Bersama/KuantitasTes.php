<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;

describe('Kuantitas (PRD §15.1)', function (): void {
    it('menyimpan 4 desimal untuk satuan desimal seperti kg dan meter', function (): void {
        expect(Kuantitas::Dari('1.25')->KeString())->toBe('1.2500')
            ->and(Kuantitas::Dari(3)->KeString())->toBe('3.0000');
    });

    it('menolak lebih dari 4 desimal', function (): void {
        Kuantitas::Dari('0.00001');
    })->throws(InvalidArgumentException::class);

    it('mengonversi satuan: 2 dus × 12 = 24 pcs', function (): void {
        expect(Kuantitas::Dari(2)->Kali(12)->KeString())->toBe('24.0000');
    });

    it('mendukung mutasi keluar (negatif) untuk ledger stok', function (): void {
        $keluar = Kuantitas::Dari('1.5')->Negasi();

        expect($keluar->BernilaiNegatif())->toBeTrue()
            ->and(Kuantitas::Dari(10)->Tambah($keluar)->KeString())->toBe('8.5000');
    });
});
