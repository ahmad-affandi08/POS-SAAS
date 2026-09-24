<?php

declare(strict_types=1);

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Harga\Data\DataBarisProdukHarga;
use App\Domain\Katalog\Harga\Data\DataDaftarHargaResolusi;
use App\Domain\Katalog\Harga\Data\DataKatalogHarga;
use App\Domain\Katalog\Harga\Data\DataPermintaanHarga;
use App\Domain\Katalog\Harga\Layanan\PenentuHarga;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;

/*
 * Test vector harga bersama (F-03 price engine lapis 3–5, CLAUDE.md #18): setiap kasus di
 * Spesifikasi/VektorUjiKalkulasi/Harga/*.json dijalankan lewat `PenentuHarga` dan hasilnya harus sama persis dengan
 * `Harapan`. Vektor yang sama dijalankan Dart di Paket/MesinKasir/test/Harga/VektorUjiHarga_test.dart.
 */
$berkasVektorHarga = glob(dirname(__DIR__, 5).'/Spesifikasi/VektorUjiKalkulasi/Harga/*.json') ?: [];

/**
 * @param  array<string, mixed>  $katalog
 */
function BacaKatalogHargaVektor(array $katalog): DataKatalogHarga
{
    /** @var list<array{Uuid: string, Aktif: bool, UuidOutlet: list<string>|null, Kanal: string|null, TierPelanggan: string|null, MulaiPada: string|null, SelesaiPada: string|null, Prioritas: int}> $daftarHarga */
    $daftarHarga = $katalog['DaftarHarga'];
    /** @var list<array{UuidProduk: string, UuidProdukSatuan: string, UuidDaftarHarga: string|null, JumlahMinimum: string, Harga: string}> $produkHarga */
    $produkHarga = $katalog['ProdukHarga'];

    return new DataKatalogHarga(
        array_map(fn (array $daftar): DataDaftarHargaResolusi => new DataDaftarHargaResolusi(
            $daftar['Uuid'],
            $daftar['Aktif'],
            $daftar['UuidOutlet'],
            $daftar['Kanal'] === null ? null : KanalPenjualan::from($daftar['Kanal']),
            $daftar['TierPelanggan'],
            $daftar['MulaiPada'] === null ? null : CarbonImmutable::parse($daftar['MulaiPada']),
            $daftar['SelesaiPada'] === null ? null : CarbonImmutable::parse($daftar['SelesaiPada']),
            $daftar['Prioritas'],
        ), $daftarHarga),
        array_map(fn (array $baris): DataBarisProdukHarga => new DataBarisProdukHarga(
            $baris['UuidProduk'],
            $baris['UuidProdukSatuan'],
            $baris['UuidDaftarHarga'],
            Kuantitas::Dari($baris['JumlahMinimum']),
            Uang::Dari($baris['Harga']),
        ), $produkHarga),
    );
}

it('menemukan test vector harga F-03', function () use ($berkasVektorHarga): void {
    expect(count($berkasVektorHarga))->toBeGreaterThanOrEqual(11);
});

describe('test vector harga (PenentuHarga lapis 3–5)', function () use ($berkasVektorHarga): void {
    foreach ($berkasVektorHarga as $berkas) {
        /** @var array{Id: string, Keterangan: string, Katalog: array<string, mixed>, Kasus: list<array{Nama: string, Masukan: array{UuidProduk: string, UuidProdukSatuan: string, Jumlah: string, UuidOutlet: string|null, Kanal: string|null, TierPelanggan: string|null, Waktu: string}, Harapan: array<string, string|null>}>} $vektor */
        $vektor = json_decode((string) file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        it("vektor {$vektor['Id']}: nama berkas sama dengan Id dan berbagian wajib", function () use ($berkas, $vektor): void {
            expect(basename($berkas))->toBe($vektor['Id'].'.json')
                ->and($vektor)->toHaveKeys(['Id', 'Keterangan', 'Katalog', 'Kasus'])
                ->and($vektor['Kasus'])->not->toBeEmpty();
        });

        foreach ($vektor['Kasus'] as $kasus) {
            it("vektor {$vektor['Id']}: {$kasus['Nama']}", function () use ($vektor, $kasus): void {
                $masukan = $kasus['Masukan'];
                $hasil = (new PenentuHarga)->Tentukan(BacaKatalogHargaVektor($vektor['Katalog']), new DataPermintaanHarga(
                    $masukan['UuidProduk'],
                    $masukan['UuidProdukSatuan'],
                    Kuantitas::Dari($masukan['Jumlah']),
                    $masukan['UuidOutlet'],
                    $masukan['Kanal'] === null ? null : KanalPenjualan::from($masukan['Kanal']),
                    $masukan['TierPelanggan'],
                    CarbonImmutable::parse($masukan['Waktu']),
                ));
                $harapan = $kasus['Harapan'];

                if (array_key_exists('Galat', $harapan)) {
                    expect($harapan['Galat'])->toBe('HargaTidakDitemukan')
                        ->and($hasil)->toBeNull();

                    return;
                }

                expect($hasil)->not->toBeNull()
                    ->and([
                        'Harga' => $hasil?->harga->KeString(),
                        'Sumber' => $hasil?->sumber->value,
                        'UuidDaftarHarga' => $hasil?->uuidDaftarHarga,
                        'JumlahMinimum' => $hasil?->jumlahMinimum->KeString(),
                    ])->toBe([
                        'Harga' => $harapan['Harga'],
                        'Sumber' => $harapan['Sumber'],
                        'UuidDaftarHarga' => $harapan['UuidDaftarHarga'],
                        'JumlahMinimum' => $harapan['JumlahMinimum'],
                    ]);
            });
        }
    }
});
