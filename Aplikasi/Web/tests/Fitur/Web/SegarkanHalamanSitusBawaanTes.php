<?php

declare(strict_types=1);

use App\Domain\Pengelola\Konten\Aksi\SiapkanHalamanSitusBawaan;
use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Model\HalamanSitus;

/*
 * D-21/D-25: `SiapkanHalamanSitusBawaan` sengaja tidak menyentuh halaman yang sudah ada, dan halaman publik
 * selalu diambil dari baris database bila ada. Tanpa perintah ini, copy bawaan yang diperbarui tidak pernah
 * tampil di pemasangan yang halamannya sudah terlanjur dibuat.
 */

/**
 * Urutkan kunci array secara rekursif.
 *
 * Kolom JSON MySQL menormalkan urutan kunci objek (panjang kunci dulu, lalu alfabet), sedangkan array sumber di
 * PHP memakai urutan penulisan. Perbandingan identik (`toBe`) peka urutan kunci, jadi test ini pernah lolos di
 * SQLite lokal tetapi gagal di MySQL CI. Yang dijanjikan kontraknya adalah isinya, bukan urutan kuncinya, jadi
 * kedua sisi dinormalkan dulu — nilai & strukturnya tetap dibandingkan ketat.
 *
 * @param  array<array-key, mixed>  $data
 * @return array<array-key, mixed>
 */
function UrutkanKunciSitus(array $data): array
{
    ksort($data);

    foreach ($data as $kunci => $nilai) {
        if (is_array($nilai)) {
            $data[$kunci] = UrutkanKunciSitus($nilai);
        }
    }

    return $data;
}

it('menimpa halaman yang sudah ada dengan isi bawaan terbaru dan langsung menerbitkannya', function (): void {
    app(SiapkanHalamanSitusBawaan::class)->Jalankan();

    HalamanSitus::query()->where('Slug', 'beranda')->update([
        'JudulTerbit' => 'Copy lama',
        'BagianTerbit' => [['Jenis' => 'Cta', 'Judul' => 'Copy lama']],
        'BagianDraf' => [['Jenis' => 'Cta', 'Judul' => 'Copy lama']],
    ]);

    $this->artisan('situs:segarkan-bawaan', ['--paksa' => true])->assertSuccessful();

    $beranda = HalamanSitus::query()->where('Slug', 'beranda')->firstOrFail();
    $bawaan = KontenSitusBawaan::AmbilHalaman()['beranda'];

    $bagianBawaan = UrutkanKunciSitus($bawaan['Bagian']);

    expect(UrutkanKunciSitus($beranda->BagianTerbit))->toBe($bagianBawaan)
        ->and(UrutkanKunciSitus($beranda->BagianDraf))->toBe($bagianBawaan)
        ->and($beranda->JudulTerbit)->toBe($bawaan['Judul'])
        ->and($beranda->DiterbitkanPada)->not->toBeNull();
});

it('tanpa --paksa dan tanpa konfirmasi tidak mengubah apa pun', function (): void {
    app(SiapkanHalamanSitusBawaan::class)->Jalankan();
    HalamanSitus::query()->where('Slug', 'beranda')->update(['JudulTerbit' => 'Copy lama']);

    $this->artisan('situs:segarkan-bawaan')
        ->expectsConfirmation('Lanjutkan?', 'no')
        ->assertSuccessful();

    expect(HalamanSitus::query()->where('Slug', 'beranda')->value('JudulTerbit'))->toBe('Copy lama');
});

it('opsi --halaman hanya menyegarkan slug yang disebut', function (): void {
    app(SiapkanHalamanSitusBawaan::class)->Jalankan();
    HalamanSitus::query()->whereIn('Slug', ['beranda', 'fitur'])->update(['JudulTerbit' => 'Copy lama']);

    $this->artisan('situs:segarkan-bawaan', ['--halaman' => ['beranda'], '--paksa' => true])->assertSuccessful();

    expect(HalamanSitus::query()->where('Slug', 'beranda')->value('JudulTerbit'))->not->toBe('Copy lama');
    expect(HalamanSitus::query()->where('Slug', 'fitur')->value('JudulTerbit'))->toBe('Copy lama');
});

it('slug yang tidak dikenal ditolak tanpa mengubah apa pun', function (): void {
    app(SiapkanHalamanSitusBawaan::class)->Jalankan();
    HalamanSitus::query()->where('Slug', 'beranda')->update(['JudulTerbit' => 'Copy lama']);

    $this->artisan('situs:segarkan-bawaan', ['--halaman' => ['tidak-ada'], '--paksa' => true])->assertFailed();

    expect(HalamanSitus::query()->where('Slug', 'beranda')->value('JudulTerbit'))->toBe('Copy lama');
});

it('tanpa halaman bawaan di database tidak melakukan apa-apa', function (): void {
    expect(HalamanSitus::query()->count())->toBe(0);

    $this->artisan('situs:segarkan-bawaan', ['--paksa' => true])->assertSuccessful();

    expect(HalamanSitus::query()->count())->toBe(0);
});
