<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto bukti kas masuk/keluar (K-18): JPEG base64 dari item outbox `MutasiKas.Catat`, divalidasi (tanda tangan JPEG,
 * ≤ `config('kasir.UkuranMaksimalBuktiKasKb')`), disimpan di disk privat `config('kasir.DiskBuktiKas')` dengan nama
 * acak per tenant. Diunduh hanya lewat rute berizin; tidak pernah ditulis ke log.
 */
final class PenyimpanBuktiKas
{
    public function Simpan(int $idTenant, string $base64): string
    {
        $bait = base64_decode($base64, true);

        if ($bait === false || ! str_starts_with($bait, "\xFF\xD8\xFF")) {
            throw new PelanggaranAturanBisnis('BuktiTidakValid', 'Foto bukti kas harus gambar JPEG.', 'Bukti');
        }

        if (strlen($bait) > (int) config('kasir.UkuranMaksimalBuktiKasKb') * 1024) {
            throw new PelanggaranAturanBisnis('BuktiTerlaluBesar', 'Ukuran foto bukti kas melebihi batas.', 'Bukti');
        }

        $path = "kasir/bukti-kas/{$idTenant}/".Str::ulid().'.jpg';

        if (! $this->AmbilDisk()->put($path, $bait)) {
            throw new RuntimeException('Foto bukti kas gagal disimpan.');
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
        $disk = Storage::disk((string) config('kasir.DiskBuktiKas'));

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Disk bukti kas tidak dikenal.');
        }

        return $disk;
    }
}
