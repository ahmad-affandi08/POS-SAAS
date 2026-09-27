<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Enum\StatusArtikel;
use App\Domain\Situs\Model\ArtikelSitus;

/**
 * Situs bagian B2: menerbitkan artikel (tampil di `/blog`) atau menariknya kembali ke draf. Tanggal terbit diisi saat
 * pertama terbit dan tidak berubah saat diterbitkan ulang (tautan & urutan tetap).
 */
final class UbahStatusArtikelSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, ArtikelSitus $artikel, StatusArtikel $status): ArtikelSitus
    {
        $lama = $artikel->Status;
        $artikel->Status = $status;
        $artikel->IdPenggunaPengelolaPengubah = $pelaku->Id;

        if ($status === StatusArtikel::Terbit && $artikel->DiterbitkanPada === null) {
            $artikel->DiterbitkanPada = now();
        }

        $artikel->save();
        $this->audit->Catat($status === StatusArtikel::Terbit ? 'situs.artikel.terbitkan' : 'situs.artikel.tarik', $artikel, ['Status' => $lama->value], ['Status' => $status->value]);

        return $artikel;
    }
}
