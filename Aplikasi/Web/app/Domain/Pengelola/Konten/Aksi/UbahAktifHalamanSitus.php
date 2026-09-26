<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\HalamanSitus;

/**
 * D-21: menyembunyikan (nonaktif) atau menampilkan lagi halaman situs tanpa menghapus isinya. Beranda selalu aktif.
 */
final class UbahAktifHalamanSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $halaman, bool $aktif): HalamanSitus
    {
        if (! $aktif && $halaman->Slug === HalamanSitus::SLUG_BERANDA) {
            throw new PelanggaranAturanBisnis('BerandaWajibAktif', 'Beranda tidak bisa disembunyikan.');
        }

        $lama = $halaman->Aktif;
        $halaman->forceFill(['Aktif' => $aktif, 'IdPenggunaPengelolaPengubah' => $pelaku->Id])->save();
        $this->audit->Catat($aktif ? 'situs.halaman.tampilkan' : 'situs.halaman.sembunyikan', $halaman, ['Aktif' => $lama], ['Aktif' => $aktif]);

        return $halaman;
    }
}
