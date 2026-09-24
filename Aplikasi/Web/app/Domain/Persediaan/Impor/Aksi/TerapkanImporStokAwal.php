<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Layanan\PengirimTugasImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use Illuminate\Support\Facades\DB;

/**
 * F-05a impor stok awal langkah 4 (DesainF05a C.7): mulai membuat draf stok awal dari baris Valid. Syarat: status
 * Pratinjau dan ada baris valid (`StatusImporTidakValid`). Status → Menerapkan, lalu `TerapkanImporStokAwalTugas`
 * dijalankan langsung (≤ `persediaan.Impor.BatasBarisSinkron` baris) atau lewat antrean. Draf dibuat per lokasi
 * stok lewat `SimpanStokAwal`; impor **tidak pernah memposting**. Audit `stok-awal.impor.terapkan` (mulai).
 */
final class TerapkanImporStokAwal
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(ImporStokAwal $impor): ImporStokAwal
    {
        $impor = DB::transaction(function () use ($impor): ImporStokAwal {
            $impor = ImporStokAwal::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if ($impor->Status !== StatusImporStokAwal::Pratinjau) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', "Impor berstatus {$impor->Status->AmbilLabel()} tidak bisa diterapkan.", 'Impor');
            }

            if ($impor->JumlahValid === 0) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', 'Tidak ada baris valid untuk dijadikan draf. Perbaiki berkas lalu unggah ulang.', 'Impor');
            }

            $impor->UbahStatus(StatusImporStokAwal::Menerapkan);
            $impor->DiterapkanPada = now();
            $impor->save();
            $this->audit->Catat('stok-awal.impor.terapkan', $impor, null, ['Tahap' => 'Mulai', 'JumlahValid' => $impor->JumlahValid]);

            return $impor;
        });

        PengirimTugasImporStokAwal::KirimPenerapan($impor);

        return $impor->refresh();
    }
}
