<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use Illuminate\Support\Facades\DB;

/**
 * F-05a impor stok awal (DesainF05a C.7): batalkan impor sebelum draf dibuat (dari MenungguPemetaan atau
 * Pratinjau). Tidak ada dokumen stok awal yang dibuat; berkas & laporan tetap ada sampai masa simpan habis.
 * Audit `stok-awal.impor.batalkan`.
 */
final class BatalkanImporStokAwal
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(ImporStokAwal $impor): ImporStokAwal
    {
        return DB::transaction(function () use ($impor): ImporStokAwal {
            $impor = ImporStokAwal::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if (! $impor->Status->BisaBerubahKe(StatusImporStokAwal::Dibatalkan)) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', "Impor berstatus {$impor->Status->AmbilLabel()} tidak bisa dibatalkan.", 'Impor');
            }

            $statusLama = $impor->Status;
            $impor->UbahStatus(StatusImporStokAwal::Dibatalkan);
            $impor->save();
            $this->audit->Catat('stok-awal.impor.batalkan', $impor, ['Status' => $statusLama->value], ['Status' => StatusImporStokAwal::Dibatalkan->value]);

            return $impor;
        });
    }
}
