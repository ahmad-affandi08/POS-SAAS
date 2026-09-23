<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus merek yang belum pernah dipakai outlet mana pun (termasuk outlet arsip). Merek yang sudah dipakai
 * tidak bisa dihapus agar riwayat outlet tetap utuh. Minimal satu merek harus ada.
 */
final class HapusMerek
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Merek $merek): void
    {
        DB::transaction(function () use ($merek): void {
            $merek = Merek::query()->lockForUpdate()->findOrFail($merek->Id);

            if (Outlet::query()->where('IdMerek', $merek->Id)->exists()) {
                throw new PelanggaranAturanBisnis('MerekDipakai', "Merek {$merek->Nama} dipakai outlet sehingga tidak bisa dihapus. Pindahkan outletnya ke merek lain dulu.");
            }

            if (Merek::query()->count() <= 1) {
                throw new PelanggaranAturanBisnis('MerekTerakhir', 'Usaha Anda harus punya minimal satu merek.');
            }

            $this->audit->Catat('merek.hapus', $merek, nilaiLama: ['Nama' => $merek->Nama]);
            $merek->delete();
        });
    }
}
