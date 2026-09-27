<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\ArtikelSitus;

/**
 * Situs bagian B2: menghapus artikel. Artikel terbit harus ditarik ke draf dulu agar tidak ada tautan yang mati tanpa
 * disadari.
 */
final class HapusArtikelSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, ArtikelSitus $artikel): void
    {
        if ($artikel->CekTerbit()) {
            throw new PelanggaranAturanBisnis('ArtikelMasihTerbit', 'Tarik artikel ke draf dulu sebelum menghapusnya.');
        }

        $this->audit->Catat('situs.artikel.hapus', $artikel, nilaiLama: ['Slug' => $artikel->Slug, 'Judul' => $artikel->Judul], idPelaku: $pelaku->Id);
        $artikel->delete();
    }
}
