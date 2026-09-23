<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\LokasiStokOutlet;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * Mengarsipkan atau memulihkan lokasi stok (F-02). Lokasi stok tidak dihapus karena dirujuk mutasi stok.
 * BR-02.4: lokasi stok jual terakhir di outlet aktif tidak bisa diarsipkan.
 */
final class UbahStatusGudang
{
    public function __construct(
        private readonly LokasiStokOutlet $lokasiStok,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Outlet $outlet, Gudang $gudang, StatusOrganisasi $tujuan): Gudang
    {
        return DB::transaction(function () use ($outlet, $gudang, $tujuan): Gudang {
            $gudang = Gudang::query()->lockForUpdate()->findOrFail($gudang->Id);
            $asal = $gudang->Status;

            if (! $asal->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakBerubah', "Lokasi stok {$gudang->Nama} sudah berstatus {$tujuan->AmbilLabel()}.");
            }

            if ($tujuan === StatusOrganisasi::Aktif && $outlet->Status !== StatusOrganisasi::Aktif) {
                throw new PelanggaranAturanBisnis('OutletDiarsipkan', 'Outlet ini diarsipkan. Pulihkan outlet dulu.');
            }

            if ($tujuan === StatusOrganisasi::Diarsipkan && $gudang->Jenis->CekLokasiStokJual()
                && $this->lokasiStok->HitungLokasiStokJual($outlet->Id, kecualiIdGudang: $gudang->Id) === 0) {
                throw new PelanggaranAturanBisnis('BR-02.4', 'Ini lokasi stok jual terakhir di outlet ini. Tambah lokasi lain dulu sebelum mengarsipkannya.');
            }

            $gudang->update(['Status' => $tujuan, 'DiarsipkanPada' => $tujuan === StatusOrganisasi::Diarsipkan ? now() : null]);

            $this->audit->Catat(
                $tujuan === StatusOrganisasi::Diarsipkan ? 'gudang.arsipkan' : 'gudang.pulihkan',
                $gudang,
                nilaiLama: ['Status' => $asal->value],
                nilaiBaru: ['Status' => $tujuan->value],
            );

            return $gudang;
        });
    }
}
