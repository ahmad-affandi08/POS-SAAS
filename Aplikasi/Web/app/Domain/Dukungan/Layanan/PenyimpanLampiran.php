<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Layanan;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran tiket dukungan (P-09). Disimpan di disk privat `config('dukungan.DiskLampiran')` dengan nama acak di
 * folder per tenant & tiket, sehingga nama asli dari pengguna tidak pernah menjadi path. Diunduh hanya lewat rute
 * yang sudah memeriksa hak akses tiket. Validasi jenis & ukuran dilakukan di Permintaan.
 */
final class PenyimpanLampiran
{
    /**
     * @param  list<UploadedFile>  $berkas
     * @return list<array{Uuid: string, NamaAsli: string, Mime: string, UkuranByte: int, Path: string}>
     */
    public function Simpan(int $idTenant, string $uuidTiket, array $berkas): array
    {
        $hasil = [];

        try {
            foreach ($berkas as $file) {
                $uuid = (string) Str::ulid();
                $ekstensi = Str::lower($file->guessExtension() ?? $file->getClientOriginalExtension());
                $path = $this->AmbilDisk()->putFileAs("dukungan/{$idTenant}/{$uuidTiket}", $file, "{$uuid}.{$ekstensi}");

                if ($path === false) {
                    throw new RuntimeException('Lampiran gagal disimpan.');
                }

                $hasil[] = [
                    'Uuid' => $uuid,
                    'NamaAsli' => Str::limit(basename($file->getClientOriginalName()), 150, ''),
                    'Mime' => (string) $file->getMimeType(),
                    'UkuranByte' => (int) $file->getSize(),
                    'Path' => $path,
                ];
            }
        } catch (RuntimeException $galat) {
            $this->Hapus($hasil);

            throw $galat;
        }

        return $hasil;
    }

    /**
     * Membersihkan berkas bila transaksi yang menyimpannya gagal.
     *
     * @param  list<array{Path: string}>  $lampiran
     */
    public function Hapus(array $lampiran): void
    {
        if ($lampiran !== []) {
            $this->AmbilDisk()->delete(array_column($lampiran, 'Path'));
        }
    }

    /**
     * @param  array{NamaAsli: string, Mime: string, Path: string}  $lampiran
     */
    public function Unduh(array $lampiran): StreamedResponse
    {
        abort_unless($this->AmbilDisk()->exists($lampiran['Path']), 404);

        return $this->AmbilDisk()->download($lampiran['Path'], $lampiran['NamaAsli'], [
            'Content-Type' => $lampiran['Mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function AmbilDisk(): FilesystemAdapter
    {
        $disk = Storage::disk((string) config('dukungan.DiskLampiran'));

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Disk lampiran dukungan tidak dikenal.');
        }

        return $disk;
    }
}
