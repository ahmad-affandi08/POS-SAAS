<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\HalamanSitus;
use Illuminate\Support\Facades\DB;

/**
 * D-21: menerbitkan draf halaman situs (salin draf ke kolom terbit). Versi terbit sebelumnya digantikan.
 */
final class TerbitkanHalamanSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $halaman): HalamanSitus
    {
        return DB::transaction(function () use ($pelaku, $halaman): HalamanSitus {
            $halaman = HalamanSitus::query()->whereKey($halaman->Id)->lockForUpdate()->firstOrFail();
            $halaman->fill([
                'BagianTerbit' => $halaman->BagianDraf,
                'JudulTerbit' => $halaman->Judul,
                'JudulSeoTerbit' => $halaman->JudulSeo,
                'DeskripsiSeoTerbit' => $halaman->DeskripsiSeo,
                'UuidGambarOgTerbit' => $halaman->UuidGambarOg,
                'DiterbitkanPada' => now(),
                'IdPenggunaPengelolaPenerbit' => $pelaku->Id,
            ])->save();

            $this->audit->Catat('situs.halaman.terbitkan', $halaman, nilaiBaru: ['Slug' => $halaman->Slug, 'Judul' => $halaman->Judul]);

            return $halaman;
        });
    }
}
