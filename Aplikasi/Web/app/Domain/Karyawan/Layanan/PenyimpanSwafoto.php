<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Swafoto absensi (F-18): JPEG base64 dari item outbox, divalidasi (tanda tangan JPEG, ≤
 * `config('karyawan.UkuranMaksimalSwafotoKb')`), disimpan di disk privat `config('karyawan.DiskSwafoto')` dengan nama
 * acak per tenant. Diunduh hanya lewat rute berizin; tidak pernah ditulis ke log.
 */
final class PenyimpanSwafoto
{
    public function Simpan(int $idTenant, string $base64): string
    {
        $bait = base64_decode($base64, true);

        if ($bait === false || ! str_starts_with($bait, "\xFF\xD8\xFF")) {
            throw new PelanggaranAturanBisnis('SwafotoTidakValid', 'Swafoto harus gambar JPEG.', 'Swafoto');
        }

        if (strlen($bait) > (int) config('karyawan.UkuranMaksimalSwafotoKb') * 1024) {
            throw new PelanggaranAturanBisnis('SwafotoTerlaluBesar', 'Ukuran swafoto melebihi batas.', 'Swafoto');
        }

        $path = "karyawan/swafoto/{$idTenant}/".Str::ulid().'.jpg';

        if (! $this->AmbilDisk()->put($path, $bait)) {
            throw new RuntimeException('Swafoto gagal disimpan.');
        }

        return $path;
    }

    /** Membersihkan berkas bila transaksi yang menyimpannya gagal atau item ternyata duplikat. */
    public function Hapus(?string $path): void
    {
        if ($path !== null) {
            $this->AmbilDisk()->delete($path);
        }
    }

    public function Unduh(?string $path): StreamedResponse
    {
        abort_if($path === null || ! $this->AmbilDisk()->exists($path), 404);

        return $this->AmbilDisk()->response($path, basename($path), [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function AmbilDisk(): FilesystemAdapter
    {
        $disk = Storage::disk((string) config('karyawan.DiskSwafoto'));

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Disk swafoto tidak dikenal.');
        }

        return $disk;
    }
}
