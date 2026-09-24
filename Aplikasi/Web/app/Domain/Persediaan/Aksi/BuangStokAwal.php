<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use Illuminate\Support\Facades\DB;

/**
 * Membuang draf stok awal: Draf → Dibuang (DesainF05a C.6.2, H-14). Dokumen transaksi tidak pernah dihapus; draf
 * yang dibuang tetap tersimpan beserta riwayatnya, tanpa efek stok maupun jurnal. Selain Draf = `StatusTidakSesuai`;
 * yang sudah Dibuang dikembalikan apa adanya (idempoten). Audit `stok-awal.buang`.
 */
final class BuangStokAwal
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(StokAwal $stokAwal): StokAwal
    {
        return DB::transaction(function () use ($stokAwal): StokAwal {
            $stokAwal = StokAwal::query()->whereKey($stokAwal->Id)->lockForUpdate()->firstOrFail();

            if ($stokAwal->Status === StatusStokAwal::Dibuang) {
                return $stokAwal;
            }

            if ($stokAwal->Status !== StatusStokAwal::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Stok awal berstatus {$stokAwal->Status->AmbilLabel()} tidak bisa dibuang. Hanya draf yang bisa dibuang.");
            }

            $oleh = $this->audit->AmbilIdPengguna();
            $stokAwal->UbahStatus(StatusStokAwal::Dibuang);
            $stokAwal->DiubahOleh = $oleh;
            $stokAwal->save();

            $this->riwayat->Catat(StokAwal::JENIS_DOKUMEN, $stokAwal->Id, StatusStokAwal::Draf->value, StatusStokAwal::Dibuang->value, $oleh);
            $this->audit->Catat('stok-awal.buang', $stokAwal, ['Status' => StatusStokAwal::Draf->value], [
                'Status' => StatusStokAwal::Dibuang->value,
                'JumlahBaris' => $stokAwal->JumlahBaris,
                'TotalNilai' => $stokAwal->TotalNilai,
            ]);

            return $stokAwal;
        });
    }
}
