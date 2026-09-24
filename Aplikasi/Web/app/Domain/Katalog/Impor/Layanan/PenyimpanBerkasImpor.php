<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Berkas impor produk di disk privat (F-03): `impor/{IdTenant}/{ulid}.{ext}`, tidak pernah punya URL publik; hanya
 * dibaca pekerjaan impor tenant itu. Disk `katalog.Impor.Disk` (bawaan `local` = storage/app/private). Untuk disk
 * non-lokal (S3), berkas disalin sementara ke folder lokal selama dibaca.
 */
final class PenyimpanBerkasImpor
{
    public static function AmbilNamaDisk(): string
    {
        return (string) config('katalog.Impor.Disk', 'local');
    }

    public function Disk(): Filesystem
    {
        return Storage::disk(self::AmbilNamaDisk());
    }

    /** Simpan isi berkas (sudah dinormalisasi) untuk tenant; mengembalikan path relatif di disk. */
    public function Simpan(int $idTenant, string $isi, string $ekstensi): string
    {
        $path = 'impor/'.$idTenant.'/'.Str::lower((string) Str::ulid()).'.'.$ekstensi;

        if (! $this->Disk()->put($path, $isi)) {
            throw new RuntimeException('Berkas impor gagal disimpan.');
        }

        return $path;
    }

    public function Hapus(string $path): void
    {
        $this->Disk()->delete($path);
    }

    /**
     * Jalankan `$kerja` dengan path lokal berkas (openspout butuh berkas lokal).
     *
     * @template T
     *
     * @param  Closure(string): T  $kerja
     * @return T
     */
    public function DenganPathLokal(string $path, Closure $kerja): mixed
    {
        if (config('filesystems.disks.'.self::AmbilNamaDisk().'.driver') === 'local') {
            return $kerja(Storage::disk(self::AmbilNamaDisk())->path($path));
        }

        $sementara = storage_path('app/impor-sementara/'.Str::lower((string) Str::ulid()).'.'.pathinfo($path, PATHINFO_EXTENSION));
        @mkdir(dirname($sementara), 0700, true);
        $sumber = $this->Disk()->readStream($path);

        if (! is_resource($sumber)) {
            throw new RuntimeException('Berkas impor tidak ditemukan.');
        }

        file_put_contents($sementara, $sumber);

        try {
            return $kerja($sementara);
        } finally {
            @unlink($sementara);
        }
    }
}
