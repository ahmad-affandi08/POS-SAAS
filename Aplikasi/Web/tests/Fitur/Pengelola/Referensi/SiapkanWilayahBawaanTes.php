<?php

declare(strict_types=1);

use App\Domain\Pengelola\Referensi\Aksi\SiapkanWilayahBawaan;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Kueri\WilayahKota;
use App\Domain\Referensi\Model\Wilayah;

/**
 * @param  array<mixed>  $wilayah  baris wilayah apa adanya (termasuk yang sengaja tidak valid)
 */
function TulisJsonWilayah(array $wilayah): string
{
    $path = tempnam(sys_get_temp_dir(), 'wilayah').'.json';
    file_put_contents($path, json_encode(['Wilayah' => $wilayah]));

    return $path;
}

describe('Wilayah awal wajib (P-02, prasyarat pendaftaran F-00)', function (): void {
    it('memuat 38 provinsi dan 514 kabupaten/kota Kepmendagri 2025 sekali saja', function (): void {
        expect(app(SiapkanWilayahBawaan::class)->Jalankan())->toBe(552)
            ->and(app(SiapkanWilayahBawaan::class)->Jalankan())->toBe(0);

        expect(Wilayah::query()->where('Tingkat', TingkatWilayah::Provinsi->value)->count())->toBe(38)
            ->and(Wilayah::query()->where('Tingkat', TingkatWilayah::KabupatenKota->value)->count())->toBe(514)
            ->and(Wilayah::query()->where('Kode', '33.74')->sole()->Nama)->toBe('Kota Semarang')
            ->and(Wilayah::query()->where('Kode', '33.74')->sole()->KodeInduk)->toBe('33');
        expect(app(WilayahKota::class)->Cari('33.74'))->not->toBeNull();
    });

    it('zona waktu mengikuti provinsi: WIB barat, WITA tengah, WIT timur', function (): void {
        app(SiapkanWilayahBawaan::class)->Jalankan();

        $zona = fn (string $kode) => Wilayah::query()->where('Kode', $kode)->sole()->ZonaWaktu;
        expect($zona('31.71'))->toBe(ZonaWaktu::Wib)
            ->and($zona('61.71'))->toBe(ZonaWaktu::Wib)
            ->and($zona('51.71'))->toBe(ZonaWaktu::Wita)
            ->and($zona('73.71'))->toBe(ZonaWaktu::Wita)
            ->and($zona('64.72'))->toBe(ZonaWaktu::Wita)
            ->and($zona('81.71'))->toBe(ZonaWaktu::Wit)
            ->and($zona('91.71'))->toBe(ZonaWaktu::Wit)
            ->and($zona('96.71'))->toBe(ZonaWaktu::Wit);
        expect(Wilayah::query()->where('Tingkat', TingkatWilayah::KabupatenKota->value)
            ->whereNotIn('KodeInduk', Wilayah::query()->where('Tingkat', TingkatWilayah::Provinsi->value)->select('Kode'))
            ->count())->toBe(0);
    });

    it('tidak mengubah wilayah yang sudah dikoreksi pengelola', function (): void {
        Wilayah::query()->create(['Kode' => '51', 'Nama' => 'Provinsi Bali', 'Tingkat' => TingkatWilayah::Provinsi, 'ZonaWaktu' => ZonaWaktu::Wita]);

        expect(app(SiapkanWilayahBawaan::class)->Jalankan())->toBe(551)
            ->and(Wilayah::query()->where('Kode', '51')->sole()->Nama)->toBe('Provinsi Bali');
    });

    it('menolak seluruh berkas bila satu baris tidak valid', function (array $wilayah): void {
        expect(fn () => app(SiapkanWilayahBawaan::class)->Jalankan(TulisJsonWilayah($wilayah)))
            ->toThrow(RuntimeException::class);

        expect(Wilayah::query()->count())->toBe(0);
    })->with([
        'induk tidak ada' => [[
            ['Kode' => '33', 'Nama' => 'Jawa Tengah', 'Tingkat' => 'Provinsi', 'KodeInduk' => null, 'ZonaWaktu' => 'WIB'],
            ['Kode' => '34.01', 'Nama' => 'Kulon Progo', 'Tingkat' => 'KabupatenKota', 'KodeInduk' => '34', 'ZonaWaktu' => 'WIB'],
        ]],
        'zona tidak dikenal' => [[
            ['Kode' => '33', 'Nama' => 'Jawa Tengah', 'Tingkat' => 'Provinsi', 'KodeInduk' => null, 'ZonaWaktu' => 'GMT'],
        ]],
        'kode ganda' => [[
            ['Kode' => '33', 'Nama' => 'Jawa Tengah', 'Tingkat' => 'Provinsi', 'KodeInduk' => null, 'ZonaWaktu' => 'WIB'],
            ['Kode' => '33', 'Nama' => 'Jateng', 'Tingkat' => 'Provinsi', 'KodeInduk' => null, 'ZonaWaktu' => 'WIB'],
        ]],
    ]);
});
