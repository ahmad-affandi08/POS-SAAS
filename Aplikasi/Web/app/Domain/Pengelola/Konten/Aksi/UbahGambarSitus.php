<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\GambarSitus;

/**
 * D-21: mengubah teks alternatif gambar situs (aksesibilitas & SEO gambar).
 */
final class UbahGambarSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, GambarSitus $gambar, ?string $teksAlternatif): GambarSitus
    {
        $alt = is_string($teksAlternatif) ? mb_substr(trim($teksAlternatif), 0, 150) : '';
        $lama = $gambar->TeksAlternatif;
        $gambar->forceFill(['TeksAlternatif' => $alt === '' ? null : $alt])->save();
        $this->audit->Catat('situs.gambar.ubah', $gambar, ['TeksAlternatif' => $lama], ['TeksAlternatif' => $gambar->TeksAlternatif], idPelaku: $pelaku->Id);

        return $gambar;
    }
}
