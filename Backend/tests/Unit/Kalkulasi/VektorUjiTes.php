<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/*
 * Membaca semua test vector bersama di Spesifikasi/VektorUjiKalkulasi/ (PRD §23.2, Lampiran D).
 * Fase 0: memastikan setiap vektor terbaca dan semua nilai uang valid sebagai Uang.
 * Saat engine F-07 dibangun, test ini menjalankan engine dan membandingkan hasilnya dengan Harapan.
 */
$berkasVektor = glob(dirname(__DIR__, 4).'/Spesifikasi/VektorUjiKalkulasi/*.json') ?: [];

it('menemukan minimal satu test vector', function () use ($berkasVektor): void {
    expect($berkasVektor)->not->toBeEmpty();
});

describe('test vector kalkulasi', function () use ($berkasVektor): void {
    foreach ($berkasVektor as $berkas) {
        /** @var array{Id: string, Baris: list<array{Jumlah: string, HargaSatuan: string}>, Harapan: array<string, string>} $vektor */
        $vektor = json_decode((string) file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        it("vektor {$vektor['Id']} berstruktur valid dan bernilai string desimal", function () use ($berkas, $vektor): void {
            expect(basename($berkas))->toBe($vektor['Id'].'.json')
                ->and($vektor)->toHaveKeys(['Id', 'Keterangan', 'Pengaturan', 'Baris', 'Harapan']);

            foreach ($vektor['Harapan'] as $kunci => $nilai) {
                expect($nilai)->toBeString("Harapan.{$kunci} harus string desimal");
                Uang::Dari($nilai);
            }

            foreach ($vektor['Baris'] as $baris) {
                Kuantitas::Dari($baris['Jumlah']);
                Uang::Dari($baris['HargaSatuan']);
            }
        });
    }
});
