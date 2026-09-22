<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * Penjaga D-05 di level database: setiap tabel hasil migrasi harus PascalCase,
 * kecuali tabel bawaan framework yang tercantum di Alat/KonvensiPengecualian.json.
 * Juga memastikan server MySQL case-sensitive seperti produksi (PRD §13.7.5).
 */
it('semua tabel di database bernama PascalCase atau tabel framework yang dikecualikan', function (): void {
    $pengecualian = json_decode(
        (string) file_get_contents(base_path('../Alat/KonvensiPengecualian.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    )['TabelFramework'];

    $tabel = collect(DB::select('SHOW TABLES'))->map(fn (object $baris): string => (string) array_values((array) $baris)[0]);
    $melanggar = $tabel
        ->reject(fn (string $nama): bool => in_array($nama, $pengecualian, true))
        ->reject(fn (string $nama): bool => preg_match('/^[A-Z][A-Za-z0-9]*$/', $nama) === 1)
        ->values()
        ->all();

    expect($tabel)->toContain('Pengguna')
        ->and($melanggar)->toBe([]);
});

it('semua kolom tabel proyek bernama PascalCase (menangkap kolom otomatis Laravel seperti uuid/created_at)', function (): void {
    $pengecualian = json_decode(
        (string) file_get_contents(base_path('../Alat/KonvensiPengecualian.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    )['TabelFramework'];

    $melanggar = collect(DB::select(
        'SELECT TABLE_NAME AS Tabel, COLUMN_NAME AS Kolom FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
    ))
        ->reject(fn (object $baris): bool => in_array($baris->Tabel, $pengecualian, true))
        ->reject(fn (object $baris): bool => preg_match('/^[A-Z][A-Za-z0-9]*$/', $baris->Kolom) === 1)
        ->map(fn (object $baris): string => "{$baris->Tabel}.{$baris->Kolom}")
        ->values()
        ->all();

    expect($melanggar)->toBe([]);
});

it('server MySQL membedakan huruf besar-kecil nama tabel seperti produksi', function (): void {
    $nilai = (int) DB::selectOne('SELECT @@lower_case_table_names AS Nilai')->Nilai;

    expect($nilai)->toBe(0);
});
