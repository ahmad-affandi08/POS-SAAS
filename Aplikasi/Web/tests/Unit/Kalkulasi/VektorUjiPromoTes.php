<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Enum\ModeResolusiPromo;
use App\Domain\Penjualan\Kalkulasi\BarisPromo;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembayaranKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembulatanTunai;
use App\Domain\Penjualan\Kalkulasi\DataPotongan;
use App\Domain\Penjualan\Kalkulasi\DefinisiPromo;
use App\Domain\Penjualan\Kalkulasi\KonteksPromo;
use App\Domain\Penjualan\Kalkulasi\MesinPromo;
use Carbon\CarbonImmutable;

/*
 * Test vector promo bersama F-16c (CLAUDE.md #18): setiap kasus di Spesifikasi/VektorUjiKalkulasi/Promo/ dijalankan
 * lewat `MesinPromo` lalu `MesinKalkulasi`; promo terpakai (urut, potongan per baris & pesanan) dan setiap angka
 * `Harapan` wajib sama persis sampai sen. Vektor yang sama dijalankan Dart di `Paket/MesinKasir/test/Promo`.
 */
$berkasVektorPromo = glob(dirname(__DIR__, 5).'/Spesifikasi/VektorUjiKalkulasi/Promo/*.json') ?: [];

/**
 * @param  array<string, string>|null  $potongan
 */
function BacaPotonganVektorPromo(?array $potongan): ?DataPotongan
{
    if ($potongan === null) {
        return null;
    }

    return isset($potongan['Persen']) ? DataPotongan::BuatPersen($potongan['Persen']) : DataPotongan::BuatNominal(Uang::Dari($potongan['Jumlah']));
}

/**
 * @param  array<string, mixed>  $vektor
 */
function BacaDasarVektorPromo(array $vektor): DataKalkulasi
{
    /** @var array{HargaTermasukPajak: bool, PersenBiayaLayanan?: string, PembulatanTunai?: array{Kelipatan: int, Arah: string}|null} $pengaturan */
    $pengaturan = $vektor['Pengaturan'];
    /** @var list<array{Kode: string, Tarif: string, PengaliDpp?: string, DasarPengenaan?: string}> $daftarPajak */
    $daftarPajak = $vektor['Pajak'] ?? [];
    /** @var list<array{Sku: string, Jumlah: string, HargaSatuan: string, HargaPilihan?: string, DiskonManual?: array<string, string>}> $daftarBaris */
    $daftarBaris = $vektor['Baris'];
    /** @var array{Metode: string, Jumlah?: string}|list<array{Metode: string, Jumlah?: string}>|null $pembayaran */
    $pembayaran = $vektor['Pembayaran'] ?? [];
    $daftarPembayaran = isset($pembayaran['Metode']) ? [$pembayaran] : $pembayaran;
    $pembulatan = $pengaturan['PembulatanTunai'] ?? null;
    /** @var array<string, string>|null $manualPesanan */
    $manualPesanan = $vektor['DiskonManualPesanan'] ?? null;

    return new DataKalkulasi(
        $pengaturan['HargaTermasukPajak'],
        array_map(fn (array $b): DataBarisKalkulasi => new DataBarisKalkulasi(
            Kuantitas::Dari($b['Jumlah']),
            Uang::Dari($b['HargaSatuan']),
            Uang::Dari($b['HargaPilihan'] ?? '0'),
            null,
            null,
            array_values(array_filter([BacaPotonganVektorPromo($b['DiskonManual'] ?? null)])),
        ), $daftarBaris),
        array_map(function (array $pajak): DataPajakKalkulasi {
            [$pembilang, $penyebut] = explode('/', $pajak['PengaliDpp'] ?? '1/1');

            return new DataPajakKalkulasi($pajak['Kode'], $pajak['Tarif'], DasarPengenaanPajak::from($pajak['DasarPengenaan'] ?? 'Subtotal'), (int) $pembilang, (int) $penyebut);
        }, $daftarPajak),
        $pengaturan['PersenBiayaLayanan'] ?? '0',
        $pembulatan === null ? null : new DataPembulatanTunai($pembulatan['Kelipatan'], ArahPembulatan::from($pembulatan['Arah'])),
        array_values(array_filter([BacaPotonganVektorPromo($manualPesanan)])),
        array_map(fn (array $b): DataPembayaranKalkulasi => new DataPembayaranKalkulasi($b['Metode'] === 'Tunai', isset($b['Jumlah']) ? Uang::Dari($b['Jumlah']) : null), $daftarPembayaran),
    );
}

it('menemukan test vector promo F-16c', function () use ($berkasVektorPromo): void {
    expect(count($berkasVektorPromo))->toBeGreaterThanOrEqual(8);
});

describe('test vector promo', function () use ($berkasVektorPromo): void {
    foreach ($berkasVektorPromo as $berkas) {
        /** @var array<string, mixed> $vektor */
        $vektor = json_decode((string) file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);
        /** @var string $id */
        $id = $vektor['Id'];

        it("vektor {$id}: MesinPromo + MesinKalkulasi sama dengan Harapan (F-16c)", function () use ($berkas, $vektor, $id): void {
            expect(basename($berkas))->toBe($id.'.json');
            /** @var list<array{Sku: string, Kategori?: string|null}> $baris */
            $baris = $vektor['Baris'];
            /** @var array{Waktu: string, WaktuLokal: string, UuidOutlet?: string|null, Kanal?: string|null, Tier?: string|null, Voucher?: list<string>, MetodeBayar?: list<string>|null, Berpelanggan?: bool, TanggalLahir?: string|null, JumlahTransaksiPelanggan?: int|null, PemakaianPelanggan?: array<string, array{Hari: int, Promo: int}>} $konteks */
            $konteks = $vektor['Konteks'];
            /** @var list<array{Uuid: string, Kode: string, Prioritas: int, Eksklusif: bool, MulaiPada: string|null, SelesaiPada: string|null, KuotaTersisa: int|null, Definisi: array<string, mixed>}> $daftarPromo */
            $daftarPromo = $vektor['Promo'];

            $hasil = (new MesinPromo)->Terapkan(
                BacaDasarVektorPromo($vektor),
                array_map(fn (array $b): BarisPromo => new BarisPromo($b['Sku'], $b['Kategori'] ?? null), $baris),
                array_map(fn (array $p): DefinisiPromo => DefinisiPromo::Urai(
                    $p['Uuid'],
                    $p['Kode'],
                    $p['Definisi'],
                    $p['Prioritas'],
                    $p['Eksklusif'],
                    $p['MulaiPada'] === null ? null : CarbonImmutable::parse($p['MulaiPada']),
                    $p['SelesaiPada'] === null ? null : CarbonImmutable::parse($p['SelesaiPada']),
                    $p['KuotaTersisa'],
                ), $daftarPromo),
                new KonteksPromo(
                    CarbonImmutable::parse($konteks['Waktu']),
                    CarbonImmutable::parse($konteks['WaktuLokal'], 'UTC'),
                    $konteks['UuidOutlet'] ?? null,
                    isset($konteks['Kanal']) ? KanalPenjualan::from($konteks['Kanal']) : null,
                    $konteks['Tier'] ?? null,
                    $konteks['Voucher'] ?? [],
                    $konteks['MetodeBayar'] ?? null,
                    $konteks['Berpelanggan'] ?? false,
                    $konteks['TanggalLahir'] ?? null,
                    $konteks['JumlahTransaksiPelanggan'] ?? null,
                    $konteks['PemakaianPelanggan'] ?? [],
                ),
                ModeResolusiPromo::from((string) $vektor['Mode']),
            );

            /** @var array<string, mixed> $harapan */
            $harapan = $vektor['Harapan'];
            $terpakai = array_map(fn ($t): array => [
                'Kode' => $t->kode,
                'DiskonBaris' => (object) array_combine(
                    array_map(fn (int $i): string => $baris[$i]['Sku'], array_keys($t->diskonBaris)),
                    array_map(fn (Uang $u): string => $u->KeString(), array_values($t->diskonBaris)),
                ),
                'DiskonPesanan' => $t->diskonPesanan->KeString(),
            ], $hasil->terpakai);
            expect(json_decode((string) json_encode($terpakai), true))->toBe(json_decode((string) json_encode($harapan['PromoTerpakai']), true));

            // F-16c bagian 4: promo poin berlipat terpilih (tanpa kunci = tidak ada).
            $poin = $hasil->poinBerlipat === null ? null : ['Kode' => $hasil->poinBerlipat->kode, 'Pengali' => (string) $hasil->poinBerlipat->AmbilPengali()];
            expect($poin)->toBe($harapan['PoinBerlipat'] ?? null);

            $aktual = $hasil->hasil->KeLarik();

            foreach ($harapan as $kunci => $nilai) {
                if ($kunci === 'PromoTerpakai' || $kunci === 'PoinBerlipat') {
                    continue;
                }

                expect($aktual)->toHaveKey($kunci);
                expect($aktual[$kunci])->toBe($nilai, "Harapan.{$kunci} pada vektor {$id}");
            }
        });
    }
});
