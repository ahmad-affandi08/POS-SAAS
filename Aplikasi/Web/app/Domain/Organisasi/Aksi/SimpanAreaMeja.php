<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataAreaMeja;
use App\Domain\Organisasi\Layanan\PenjagaModeMeja;
use App\Domain\Organisasi\Model\AreaMeja;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * F-10a: tambah atau ubah area meja di satu outlet ("Indoor", "Teras", "VIP"). Nama unik per outlet. Menambah area
 * butuh fitur paket `pos.mode-meja` aktif di outlet itu dan outlet aktif.
 */
final class SimpanAreaMeja
{
    private const KOLOM_AUDIT = ['IdOutlet', 'Nama', 'Urutan'];

    public function __construct(
        private readonly PenjagaModeMeja $penjaga,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Outlet $outlet, ?AreaMeja $area, DataAreaMeja $data): AreaMeja
    {
        return DB::transaction(function () use ($outlet, $area, $data): AreaMeja {
            $nama = trim($data->nama);
            $dipakai = AreaMeja::query()->where('IdOutlet', $outlet->Id)->where('Nama', $nama)
                ->when($area !== null, fn ($kueri) => $kueri->whereKeyNot($area?->Id))->exists();

            if ($dipakai) {
                throw new PelanggaranAturanBisnis('NamaAreaDipakai', "Area {$nama} sudah ada di outlet ini.", 'Nama');
            }

            $isian = ['Nama' => $nama, 'Urutan' => $data->urutan];

            if ($area === null) {
                $this->penjaga->PastikanBisaMenambah($outlet);
                $area = AreaMeja::query()->create(['IdOutlet' => $outlet->Id, ...$isian]);
                $this->audit->Catat('area-meja.buat', $area, nilaiBaru: $area->only(self::KOLOM_AUDIT));

                return $area;
            }

            $area = AreaMeja::query()->lockForUpdate()->findOrFail($area->Id);
            $lama = $area->only(self::KOLOM_AUDIT);
            $area->fill($isian);
            $berubah = array_keys($area->getDirty());

            if ($berubah !== []) {
                $area->save();
                $this->audit->Catat('area-meja.ubah', $area, nilaiLama: array_intersect_key($lama, array_flip($berubah)), nilaiBaru: $area->only($berubah));
            }

            return $area;
        });
    }
}
