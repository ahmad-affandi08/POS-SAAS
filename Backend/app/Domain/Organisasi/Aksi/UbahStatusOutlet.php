<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\PemakaianBatasOrganisasi;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use Illuminate\Support\Facades\DB;

/**
 * Mengarsipkan atau memulihkan outlet (F-02). Outlet tidak pernah dihapus karena dirujuk transaksi & laporan.
 * - Minimal satu outlet aktif harus tetap ada.
 * - Memulihkan outlet memakai kuota `BatasOutlet` lagi (BR-02.1).
 */
final class UbahStatusOutlet
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianBatasOrganisasi $pemakaian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Outlet $outlet, StatusOrganisasi $tujuan): Outlet
    {
        return DB::transaction(function () use ($outlet, $tujuan): Outlet {
            // Langganan dikunci lebih dulu (urutan kunci sama dengan SimpanOutlet) agar tidak saling tunggu.
            if ($tujuan === StatusOrganisasi::Aktif && $outlet->Status !== StatusOrganisasi::Aktif) {
                $this->batasPaket->Pastikan($this->konteks->Wajib(), 'BatasOutlet', fn (): int => $this->pemakaian->HitungOutlet());
            }

            $outlet = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);
            $asal = $outlet->Status;

            if (! $asal->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakBerubah', "Outlet {$outlet->Nama} sudah berstatus {$tujuan->AmbilLabel()}.");
            }

            if ($tujuan === StatusOrganisasi::Diarsipkan && $this->pemakaian->HitungOutlet(kunci: true) <= 1) {
                throw new PelanggaranAturanBisnis('OutletTerakhir', 'Outlet ini satu-satunya outlet aktif. Tambah outlet lain dulu sebelum mengarsipkannya.');
            }

            $outlet->update([
                'Status' => $tujuan,
                'DiarsipkanPada' => $tujuan === StatusOrganisasi::Diarsipkan ? now() : null,
            ]);

            $this->audit->Catat(
                $tujuan === StatusOrganisasi::Diarsipkan ? 'outlet.arsipkan' : 'outlet.pulihkan',
                $outlet,
                nilaiLama: ['Status' => $asal->value],
                nilaiBaru: ['Status' => $tujuan->value],
            );

            return $outlet;
        });
    }
}
