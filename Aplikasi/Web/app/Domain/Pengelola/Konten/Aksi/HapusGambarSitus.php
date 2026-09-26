<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;
use App\Domain\Situs\Model\PengaturanSitus;
use Illuminate\Support\Facades\Storage;

/**
 * D-21: menghapus gambar dari pustaka situs. Ditolak bila masih dipakai halaman (draf atau terbit) atau pengaturan
 * (logo, gambar pratinjau tautan), agar situs publik tidak menampilkan gambar rusak.
 */
final class HapusGambarSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, GambarSitus $gambar): void
    {
        $pola = '%'.$gambar->Uuid.'%';
        $dipakai = HalamanSitus::query()
            ->where(fn ($k) => $k->where('BagianDraf', 'like', $pola)->orWhere('BagianTerbit', 'like', $pola)
                ->orWhere('UuidGambarOg', $gambar->Uuid)->orWhere('UuidGambarOgTerbit', $gambar->Uuid))
            ->pluck('Judul')
            ->all();

        if ($dipakai !== [] || PengaturanSitus::query()->where('Nilai', 'like', $pola)->exists()) {
            $tempat = $dipakai === [] ? 'pengaturan situs' : 'halaman '.implode(', ', array_slice($dipakai, 0, 3));

            throw new PelanggaranAturanBisnis('GambarDipakai', "Gambar masih dipakai di {$tempat}. Ganti gambarnya dulu.");
        }

        $this->audit->Catat('situs.gambar.hapus', $gambar, nilaiLama: ['NamaBerkas' => $gambar->NamaBerkas], idPelaku: $pelaku->Id);
        Storage::disk((string) config('situs.Disk'))->delete($gambar->Path);
        $gambar->delete();
    }
}
