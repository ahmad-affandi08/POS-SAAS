<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Organisasi\Model\UndanganPengguna;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus peran kustom yang tidak dipakai anggota (aktif maupun nonaktif) atau undangan mana pun.
 * Peran bawaan tidak bisa dihapus.
 */
final class HapusPeran
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Peran $peran): void
    {
        DB::transaction(function () use ($peran): void {
            $peran = Peran::query()->lockForUpdate()->findOrFail($peran->Id);
            $idTenant = $this->konteks->Wajib();

            if ($peran->Bawaan) {
                throw new PelanggaranAturanBisnis('PeranBawaan', 'Peran bawaan tidak bisa dihapus.');
            }

            $dipakai = TenantPengguna::query()->where('IdTenant', $idTenant)->where('IdPeran', $peran->Id)->exists()
                || OutletPengguna::query()->where('IdPeran', $peran->Id)->exists()
                || UndanganPengguna::query()->where('IdTenant', $idTenant)->where('IdPeran', $peran->Id)->exists();

            if ($dipakai) {
                throw new PelanggaranAturanBisnis('PeranDipakai', "Peran {$peran->Nama} masih dipakai pengguna atau undangan. Ganti peran mereka dulu.");
            }

            $this->audit->Catat('peran.hapus', $peran, nilaiLama: ['Nama' => $peran->Nama, 'Izin' => $peran->AmbilKunciIzin()]);
            $peran->delete();
        });
    }
}
