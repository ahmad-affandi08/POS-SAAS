<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus draf dokumen legal (BR-P06.1, BR-P06.4). Versi terbit tidak pernah dihapus.
 */
final class HapusDrafDokumenLegal
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DokumenLegal $dokumen): void
    {
        DB::transaction(function () use ($pelaku, $dokumen): void {
            $dokumen = DokumenLegal::query()->lockForUpdate()->findOrFail($dokumen->Id);

            if ($dokumen->Status !== StatusDokumenLegal::Draf) {
                throw new PelanggaranAturanBisnis('BR-P06.1', 'Versi yang sudah terbit tidak bisa dihapus.');
            }

            $this->audit->Catat(
                'legal.draf.hapus',
                $dokumen,
                nilaiLama: ['Jenis' => $dokumen->Jenis->value, 'Versi' => $dokumen->Versi, 'Judul' => $dokumen->Judul],
                idPelaku: $pelaku->Id,
            );
            $dokumen->delete();
        });
    }
}
