<?php

declare(strict_types=1);

use App\Domain\Katalog\Enum\JenisProduk;

/*
 * F-03 aturan per jenis produk (DesainF03 C.1). Urutan kolom: PunyaStok, BisaDijual, BolehPelacakan, BolehResep,
 * BolehKomponen, BolehPilihan, DihitungBatasSku (BR-P04.3).
 */
describe('F-03 aturan jenis produk', function (): void {
    $matriks = [
        'Stok' => [true, true, true, false, false, true, true],
        'IndukVarian' => [false, false, false, false, false, true, false],
        'Resep' => [false, true, false, true, false, true, true],
        'Produksi' => [true, true, true, true, false, true, true],
        'Paket' => [false, true, false, false, true, true, true],
        'Jasa' => [false, true, false, false, false, true, true],
        'NonStok' => [false, true, false, false, false, true, true],
        'BahanBaku' => [true, false, true, false, false, false, true],
        'Konsinyasi' => [true, true, true, false, false, true, true],
    ];

    foreach ($matriks as $nilai => $harapan) {
        it("jenis {$nilai} mengikuti tabel aturan C.1", function () use ($nilai, $harapan): void {
            $jenis = JenisProduk::from($nilai);

            expect([
                $jenis->CekPunyaStok(),
                $jenis->CekBisaDijual(),
                $jenis->CekBolehPelacakan(),
                $jenis->CekBolehResep(),
                $jenis->CekBolehKomponen(),
                $jenis->CekBolehPilihan(),
                $jenis->CekDihitungBatasSku(),
            ])->toBe($harapan);
        });
    }

    it('bahan resep/pilihan hanya BahanBaku, Stok, dan Produksi', function (): void {
        $boleh = array_values(array_filter(JenisProduk::cases(), fn (JenisProduk $jenis): bool => $jenis->CekBolehBahan()));

        expect($boleh)->toBe([JenisProduk::Stok, JenisProduk::Produksi, JenisProduk::BahanBaku]);
    });

    it('anak varian tidak boleh IndukVarian, Paket, atau BahanBaku', function (): void {
        $tidakBoleh = array_values(array_filter(JenisProduk::cases(), fn (JenisProduk $jenis): bool => ! $jenis->CekBolehAnakVarian()));

        expect($tidakBoleh)->toBe([JenisProduk::IndukVarian, JenisProduk::Paket, JenisProduk::BahanBaku]);
    });

    it('komponen paket harus bisa dijual dan bukan paket', function (): void {
        $boleh = array_values(array_filter(JenisProduk::cases(), fn (JenisProduk $jenis): bool => $jenis->CekBolehKomponenPaket()));

        expect($boleh)->toBe([JenisProduk::Stok, JenisProduk::Resep, JenisProduk::Produksi, JenisProduk::Jasa, JenisProduk::NonStok, JenisProduk::Konsinyasi]);
    });

    it('daftar aturan untuk halaman memuat semua jenis dengan label', function (): void {
        $daftar = JenisProduk::AmbilDaftarAturan();

        expect($daftar)->toHaveCount(9)
            ->and($daftar[0])->toBe([
                'Nilai' => 'Stok',
                'Label' => 'Barang stok',
                'PunyaStok' => true,
                'BisaDijual' => true,
                'BolehPelacakan' => true,
                'BolehResep' => false,
                'BolehKomponen' => false,
                'BolehPilihan' => true,
                'DihitungBatasSku' => true,
            ]);
    });
});
