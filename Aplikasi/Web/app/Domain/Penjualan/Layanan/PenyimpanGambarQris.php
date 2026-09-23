<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gambar QRIS statis (F-01 langkah 5). Disk privat `config('pembayaran.DiskGambarQris')`, nama acak di folder tenant
 * (`metode-pembayaran/{IdTenant}/{ulid}.{ext}`). Diunduh hanya lewat rute back-office di dalam scope tenant.
 */
final class PenyimpanGambarQris
{
    public function Simpan(int $idTenant, UploadedFile $berkas): string
    {
        $ekstensi = Str::lower($berkas->guessExtension() ?? $berkas->getClientOriginalExtension());
        $path = $this->AmbilDisk()->putFileAs("metode-pembayaran/{$idTenant}", $berkas, Str::lower((string) Str::ulid()).".{$ekstensi}");

        if ($path === false) {
            throw new RuntimeException('Gambar QRIS gagal disimpan.');
        }

        return $path;
    }

    public function Hapus(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->AmbilDisk()->delete($path);
        }
    }

    public function Unduh(string $path): StreamedResponse
    {
        abort_unless($this->AmbilDisk()->exists($path), 404);

        return $this->AmbilDisk()->response($path, 'qris.'.pathinfo($path, PATHINFO_EXTENSION), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function AmbilDisk(): FilesystemAdapter
    {
        $disk = Storage::disk((string) config('pembayaran.DiskGambarQris'));

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Disk gambar QRIS tidak dikenal.');
        }

        return $disk;
    }
}
