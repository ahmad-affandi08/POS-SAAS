<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\GambarSitus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * D-21: mengunggah gambar ke pustaka situs pemasaran (JPG/PNG/WebP, batas `config/situs.php`). Nama berkas di disk
 * acak (Uuid), jenis diperiksa dari isi berkas, ukuran piksel dicatat untuk `width`/`height` (tanpa pergeseran tata letak).
 */
final class UnggahGambarSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, UploadedFile $berkas, ?string $teksAlternatif): GambarSitus
    {
        $tipe = (string) $berkas->getMimeType();
        $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$tipe] ?? null;

        if ($ekstensi === null || ! in_array($tipe, (array) config('situs.TipeGambar'), true)) {
            throw new PelanggaranAturanBisnis('TipeGambarTidakValid', 'Gambar harus JPG, PNG, atau WebP.', 'Berkas');
        }

        if ($berkas->getSize() > (int) config('situs.UkuranGambarMaksKb') * 1024) {
            throw new PelanggaranAturanBisnis('GambarTerlaluBesar', 'Ukuran gambar paling besar '.((int) config('situs.UkuranGambarMaksKb') / 1024).' MB.', 'Berkas');
        }

        $ukuran = @getimagesize($berkas->getRealPath());
        $uuid = (string) Str::ulid();
        $path = Storage::disk((string) config('situs.Disk'))->putFileAs('situs', $berkas, "{$uuid}.{$ekstensi}");

        if ($path === false) {
            throw new PelanggaranAturanBisnis('GagalMenyimpan', 'Gambar gagal disimpan. Coba lagi.', 'Berkas');
        }

        $alt = is_string($teksAlternatif) ? mb_substr(trim($teksAlternatif), 0, 150) : '';
        $gambar = GambarSitus::query()->create([
            'Uuid' => $uuid,
            'Path' => $path,
            'NamaBerkas' => mb_substr($berkas->getClientOriginalName(), 0, 150),
            'TipeMime' => $tipe,
            'Ukuran' => (int) $berkas->getSize(),
            'Lebar' => is_array($ukuran) ? $ukuran[0] : null,
            'Tinggi' => is_array($ukuran) ? $ukuran[1] : null,
            'TeksAlternatif' => $alt === '' ? null : $alt,
            'IdPenggunaPengelolaPengunggah' => $pelaku->Id,
        ]);

        $this->audit->Catat('situs.gambar.unggah', $gambar, nilaiBaru: ['NamaBerkas' => $gambar->NamaBerkas, 'Ukuran' => $gambar->Ukuran]);

        return $gambar;
    }
}
