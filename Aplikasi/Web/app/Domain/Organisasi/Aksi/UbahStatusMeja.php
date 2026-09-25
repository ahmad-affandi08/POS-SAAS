<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\AreaMeja;
use App\Domain\Organisasi\Model\Meja;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * F-10a: mengarsipkan atau memulihkan meja / area meja. Tidak dihapus karena dirujuk pesanan. Area yang masih
 * punya meja aktif tidak bisa diarsipkan; meja/area hanya bisa dipulihkan di outlet aktif, dan meja hanya bila
 * areanya aktif.
 */
final class UbahStatusMeja
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function JalankanMeja(Outlet $outlet, Meja $meja, StatusOrganisasi $tujuan): Meja
    {
        return DB::transaction(function () use ($outlet, $meja, $tujuan): Meja {
            $meja = Meja::query()->lockForUpdate()->findOrFail($meja->Id);
            $asal = $meja->Status;
            $this->PastikanBisaBerubah($outlet, $asal, $tujuan, "Meja {$meja->Nama}");

            if ($tujuan === StatusOrganisasi::Aktif && $meja->IdAreaMeja !== null
                && AreaMeja::query()->whereKey($meja->IdAreaMeja)->first()?->Status !== StatusOrganisasi::Aktif) {
                throw new PelanggaranAturanBisnis('AreaDiarsipkan', 'Area meja ini diarsipkan. Pulihkan areanya dulu atau pindahkan meja ke area lain.');
            }

            $meja->update(['Status' => $tujuan, 'DiarsipkanPada' => $tujuan === StatusOrganisasi::Diarsipkan ? now() : null]);
            $this->audit->Catat($tujuan === StatusOrganisasi::Diarsipkan ? 'meja.arsipkan' : 'meja.pulihkan', $meja, nilaiLama: ['Status' => $asal->value], nilaiBaru: ['Status' => $tujuan->value]);

            return $meja;
        });
    }

    public function JalankanArea(Outlet $outlet, AreaMeja $area, StatusOrganisasi $tujuan): AreaMeja
    {
        return DB::transaction(function () use ($outlet, $area, $tujuan): AreaMeja {
            $area = AreaMeja::query()->lockForUpdate()->findOrFail($area->Id);
            $asal = $area->Status;
            $this->PastikanBisaBerubah($outlet, $asal, $tujuan, "Area {$area->Nama}");

            if ($tujuan === StatusOrganisasi::Diarsipkan && Meja::query()->where('IdAreaMeja', $area->Id)->where('Status', StatusOrganisasi::Aktif->value)->exists()) {
                throw new PelanggaranAturanBisnis('AreaMasihBerisi', "Area {$area->Nama} masih punya meja aktif. Pindahkan atau arsipkan mejanya dulu.");
            }

            $area->update(['Status' => $tujuan, 'DiarsipkanPada' => $tujuan === StatusOrganisasi::Diarsipkan ? now() : null]);
            $this->audit->Catat($tujuan === StatusOrganisasi::Diarsipkan ? 'area-meja.arsipkan' : 'area-meja.pulihkan', $area, nilaiLama: ['Status' => $asal->value], nilaiBaru: ['Status' => $tujuan->value]);

            return $area;
        });
    }

    private function PastikanBisaBerubah(Outlet $outlet, StatusOrganisasi $asal, StatusOrganisasi $tujuan, string $nama): void
    {
        if (! $asal->BisaBerubahKe($tujuan)) {
            throw new PelanggaranAturanBisnis('StatusTidakBerubah', "{$nama} sudah berstatus {$tujuan->AmbilLabel()}.");
        }

        if ($tujuan === StatusOrganisasi::Aktif && $outlet->Status !== StatusOrganisasi::Aktif) {
            throw new PelanggaranAturanBisnis('OutletDiarsipkan', 'Outlet ini diarsipkan. Pulihkan outlet dulu.');
        }
    }
}
