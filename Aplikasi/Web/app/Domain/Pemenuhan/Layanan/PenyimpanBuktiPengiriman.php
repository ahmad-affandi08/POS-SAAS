<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use GdImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto bukti serah terima pengiriman (F-10 v3.49). Foto dari kamera HP biasanya membawa EXIF berisi lokasi GPS rumah
 * pelanggan; karena itu gambar **diolah ulang** (didekode lalu disimpan ulang sebagai JPEG, sisi terpanjang dibatasi
 * `config('pemenuhan.SisiMaksimalBuktiPx')`) sehingga metadata apa pun tidak ikut tersimpan (UU PDP: data minimal).
 * Disimpan di disk privat `config('pemenuhan.DiskBukti')` dengan nama acak per tenant; diunduh hanya lewat rute
 * back-office berizin.
 */
final class PenyimpanBuktiPengiriman
{
    private const KUALITAS_JPEG = 82;

    public function Simpan(int $idTenant, UploadedFile $berkas): string
    {
        if ($berkas->getSize() > (int) config('pemenuhan.UkuranMaksimalBuktiKb') * 1024) {
            throw new PelanggaranAturanBisnis('FotoTerlaluBesar', 'Ukuran foto melebihi batas.', 'Foto');
        }

        $isi = (string) file_get_contents($berkas->getRealPath());
        $gambar = $isi === '' ? false : @imagecreatefromstring($isi);

        if (! $gambar instanceof GdImage) {
            throw new PelanggaranAturanBisnis('FotoTidakValid', 'Foto harus gambar JPEG, PNG, atau WebP.', 'Foto');
        }

        $gambar = self::Kecilkan($gambar, (int) config('pemenuhan.SisiMaksimalBuktiPx'));
        ob_start();
        imagejpeg($gambar, null, self::KUALITAS_JPEG);
        $jpeg = (string) ob_get_clean();
        $path = "pemenuhan/bukti/{$idTenant}/".Str::ulid().'.jpg';

        if ($jpeg === '' || ! $this->AmbilDisk()->put($path, $jpeg)) {
            throw new RuntimeException('Foto bukti gagal disimpan.');
        }

        return $path;
    }

    /** Membersihkan berkas bila transaksi yang menyimpannya gagal. */
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

    private static function Kecilkan(GdImage $gambar, int $sisiMaksimal): GdImage
    {
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $terpanjang = max($lebar, $tinggi);

        if ($terpanjang <= $sisiMaksimal) {
            return $gambar;
        }

        $hasil = imagescale($gambar, intdiv($lebar * $sisiMaksimal, $terpanjang), intdiv($tinggi * $sisiMaksimal, $terpanjang));

        return $hasil instanceof GdImage ? $hasil : $gambar;
    }

    private function AmbilDisk(): FilesystemAdapter
    {
        $disk = Storage::disk((string) config('pemenuhan.DiskBukti'));

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Disk bukti pengiriman tidak dikenal.');
        }

        return $disk;
    }
}
