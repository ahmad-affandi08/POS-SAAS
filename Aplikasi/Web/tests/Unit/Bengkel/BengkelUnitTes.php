<?php

declare(strict_types=1);

use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Layanan\NomorPolisi;

/*
 * Bengkel (§9.10): normalisasi nomor polisi (keunikan per tenant tidak bisa dilewati dengan beda spasi/huruf) dan mesin
 * status perintah kerja (persetujuan & penagihan punya jalurnya sendiri, Dibatalkan akhir, Ditagih hanya kembali lewat void).
 */

it('nomor polisi dirapikan ke huruf besar dengan spasi antarbagian', function (string $masukan, ?string $hasil): void {
    expect(NomorPolisi::Normalisasi($masukan))->toBe($hasil);
})->with([
    ['ad1234xy', 'AD 1234 XY'],
    ['AD 1234xy', 'AD 1234 XY'],
    [' b  9876   kjt ', 'B 9876 KJT'],
    ['H-1234-AB', 'H 1234 AB'],
    ['RI 1', 'RI 1'],
    ['CD 12 34', 'CD 1234'],
    ['', null],
    ['A1', null],
    ['AD#1234', null],
]);

it('status perintah kerja: transisi sah, tujuan tombol manual, siap tagih, dan status akhir', function (): void {
    expect(StatusPerintahKerja::Diterima->BisaBerubahKe(StatusPerintahKerja::MenungguPersetujuan))->toBeTrue()
        ->and(StatusPerintahKerja::Diterima->BisaBerubahKe(StatusPerintahKerja::Ditagih))->toBeFalse()
        ->and(StatusPerintahKerja::MenungguPersetujuan->BisaBerubahKe(StatusPerintahKerja::Dikerjakan))->toBeFalse()
        ->and(StatusPerintahKerja::Disetujui->BisaBerubahKe(StatusPerintahKerja::Ditagih))->toBeTrue()
        ->and(StatusPerintahKerja::Selesai->BisaBerubahKe(StatusPerintahKerja::Dibatalkan))->toBeFalse()
        ->and(StatusPerintahKerja::Ditagih->BisaBerubahKe(StatusPerintahKerja::Selesai))->toBeTrue()
        ->and(StatusPerintahKerja::Ditagih->BisaBerubahKe(StatusPerintahKerja::Dibatalkan))->toBeFalse()
        ->and(StatusPerintahKerja::Dibatalkan->AmbilTujuanManual())->toBe([])
        ->and(StatusPerintahKerja::Disetujui->AmbilTujuanManual())->toBe([StatusPerintahKerja::Diagnosis, StatusPerintahKerja::Dikerjakan, StatusPerintahKerja::Dibatalkan])
        ->and(StatusPerintahKerja::MenungguPersetujuan->AmbilTujuanManual())->not->toContain(StatusPerintahKerja::Disetujui)
        ->and(array_map(fn (StatusPerintahKerja $s): string => $s->value, StatusPerintahKerja::AmbilSiapTagih()))->toBe(['Disetujui', 'Dikerjakan', 'Qc', 'Selesai'])
        ->and(StatusPerintahKerja::Ditolak->CekBolehDiubah())->toBeTrue()
        ->and(StatusPerintahKerja::Disetujui->CekBolehDiubah())->toBeFalse();
});
