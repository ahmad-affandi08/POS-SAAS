<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembayaranKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use App\Domain\Penjualan\Kalkulasi\DataPotongan;
use App\Domain\Penjualan\Kalkulasi\MesinKalkulasi;

/*
 * Membaca semua test vector bersama di Spesifikasi/VektorUjiKalkulasi/ (PRD §23.2, Lampiran D, Rincian F-07a).
 * Setiap vektor diperiksa strukturnya lalu dijalankan lewat `MesinKalkulasi` (F-07a); setiap kunci `Harapan`
 * (termasuk rincian `Pajak` per kode dan `Baris` per baris) wajib sama persis sampai sen. Vektor yang sama dijalankan
 * Dart di `Paket/MesinKasir`.
 */
$berkasVektor = glob(dirname(__DIR__, 5).'/Spesifikasi/VektorUjiKalkulasi/*.json') ?: [];

/**
 * @param  array<string, mixed>|null  $potongan
 */
function BacaPotonganVektor(?array $potongan): ?DataPotongan
{
    if ($potongan === null) {
        return null;
    }

    if (isset($potongan['Persen'])) {
        /** @var string $persen */
        $persen = $potongan['Persen'];

        return DataPotongan::BuatPersen($persen);
    }

    /** @var string $jumlah */
    $jumlah = $potongan['Jumlah'];

    return DataPotongan::BuatNominal(Uang::Dari($jumlah));
}

/**
 * Menerjemahkan format vektor ke masukan mesin: promo item berlaku ke baris pertama ber-Sku sama, promo pesanan dan
 * `DiskonManualPesanan` menjadi potongan pesanan, `Pembayaran` boleh objek tunggal atau daftar.
 *
 * @param  array<string, mixed>  $vektor
 */
function BacaMasukanKalkulasiVektor(array $vektor): DataKalkulasi
{
    /** @var array{HargaTermasukPajak: bool, PersenBiayaLayanan?: string, PembulatanTunai?: array{Kelipatan: int, Arah: string}|null} $pengaturan */
    $pengaturan = $vektor['Pengaturan'];
    /** @var list<array{Kode: string, Tarif: string, PengaliDpp?: string, DasarPengenaan?: string}> $daftarPajak */
    $daftarPajak = $vektor['Pajak'] ?? [];
    /** @var list<array{Sku: string, Jumlah: string, HargaSatuan: string, HargaPilihan?: string, HargaTermasukPajak?: bool, Pajak?: list<string>, DiskonManual?: array<string, mixed>}> $daftarBaris */
    $daftarBaris = $vektor['Baris'];
    /** @var list<array<string, mixed>> $daftarPromo */
    $daftarPromo = $vektor['Promo'] ?? [];

    $potonganBaris = array_map(fn (array $baris): array => array_values(array_filter([BacaPotonganVektor($baris['DiskonManual'] ?? null)])), $daftarBaris);
    $potonganPesanan = [];

    foreach ($daftarPromo as $promo) {
        $potongan = BacaPotonganVektor($promo);
        expect($potongan)->not->toBeNull();

        if (in_array($promo['Jenis'], ['DiskonTetapItem', 'DiskonPersenItem'], true)) {
            $indeks = array_search($promo['Sku'], array_column($daftarBaris, 'Sku'), true);
            expect($indeks)->toBeInt("Promo item merujuk Sku yang tidak ada: {$promo['Sku']}");
            $potonganBaris[(int) $indeks][] = $potongan;
        } else {
            $potonganPesanan[] = $potongan;
        }
    }

    /** @var array<string, mixed>|null $diskonManualPesanan */
    $diskonManualPesanan = $vektor['DiskonManualPesanan'] ?? null;

    if ($diskonManualPesanan !== null) {
        $potonganPesanan[] = BacaPotonganVektor($diskonManualPesanan);
    }

    /** @var array{Metode: string, Jumlah?: string}|list<array{Metode: string, Jumlah?: string}>|null $pembayaran */
    $pembayaran = $vektor['Pembayaran'] ?? [];
    $daftarPembayaran = isset($pembayaran['Metode']) ? [$pembayaran] : $pembayaran;
    $pembulatanTunai = $pengaturan['PembulatanTunai'] ?? null;

    return new DataKalkulasi(
        $pengaturan['HargaTermasukPajak'],
        array_map(fn (array $baris, int $indeks): DataBarisKalkulasi => new DataBarisKalkulasi(
            Kuantitas::Dari($baris['Jumlah']),
            Uang::Dari($baris['HargaSatuan']),
            Uang::Dari($baris['HargaPilihan'] ?? '0'),
            $baris['HargaTermasukPajak'] ?? null,
            $baris['Pajak'] ?? null,
            $potonganBaris[$indeks],
        ), $daftarBaris, array_keys($daftarBaris)),
        array_map(function (array $pajak): DataPajakKalkulasi {
            [$pembilang, $penyebut] = explode('/', $pajak['PengaliDpp'] ?? '1/1');

            return new DataPajakKalkulasi(
                $pajak['Kode'],
                $pajak['Tarif'],
                DasarPengenaanPajak::from($pajak['DasarPengenaan'] ?? 'Subtotal'),
                (int) $pembilang,
                (int) $penyebut,
            );
        }, $daftarPajak),
        $pengaturan['PersenBiayaLayanan'] ?? '0',
        $pembulatanTunai === null ? null : new DataPembulatanTunai($pembulatanTunai['Kelipatan'], ArahPembulatan::from($pembulatanTunai['Arah'])),
        array_values(array_filter($potonganPesanan)),
        array_map(fn (array $bayar): DataPembayaranKalkulasi => new DataPembayaranKalkulasi(
            $bayar['Metode'] === 'Tunai',
            isset($bayar['Jumlah']) ? Uang::Dari($bayar['Jumlah']) : null,
        ), $daftarPembayaran),
    );
}

it('menemukan minimal satu test vector', function () use ($berkasVektor): void {
    expect($berkasVektor)->not->toBeEmpty();
});

describe('test vector kalkulasi', function () use ($berkasVektor): void {
    foreach ($berkasVektor as $berkas) {
        /** @var array{Id: string, Baris: list<array{Jumlah: string, HargaSatuan: string}>, Harapan: array<string, mixed>} $vektor */
        $vektor = json_decode((string) file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        it("vektor {$vektor['Id']} berstruktur valid dan bernilai string desimal", function () use ($berkas, $vektor): void {
            expect(basename($berkas))->toBe($vektor['Id'].'.json')
                ->and($vektor)->toHaveKeys(['Id', 'Keterangan', 'Pengaturan', 'Baris', 'Harapan']);

            foreach ($vektor['Harapan'] as $kunci => $nilai) {
                if (in_array($kunci, ['Pajak', 'Baris'], true)) {
                    expect($nilai)->toBeArray("Harapan.{$kunci} harus objek/daftar");
                    array_walk_recursive($nilai, function (mixed $rincian) use ($kunci): void {
                        expect($rincian)->toBeString("Harapan.{$kunci} harus berisi string desimal");
                        Uang::Dari($rincian);
                    });

                    continue;
                }

                expect($nilai)->toBeString("Harapan.{$kunci} harus string desimal");
                Uang::Dari($nilai);
            }

            foreach ($vektor['Baris'] as $baris) {
                Kuantitas::Dari($baris['Jumlah']);
                Uang::Dari($baris['HargaSatuan']);
            }
        });

        it("vektor {$vektor['Id']}: hasil MesinKalkulasi sama dengan Harapan sampai sen (F-07a)", function () use ($vektor): void {
            $hasil = (new MesinKalkulasi)->Hitung(BacaMasukanKalkulasiVektor($vektor))->KeLarik();

            foreach ($vektor['Harapan'] as $kunci => $harapan) {
                expect($hasil)->toHaveKey($kunci);

                if ($kunci === 'Baris') {
                    expect($hasil['Baris'])->toHaveCount(count($harapan));
                }

                expect($hasil[$kunci])->toBe($harapan, "Harapan.{$kunci} pada vektor {$vektor['Id']}");
            }
        });
    }
});
