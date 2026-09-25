<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * Lokasi stok "Dalam perjalanan" untuk transfer stok F-05b (PRD Rincian F-05b, §15 `Gudang.Jenis`): satu per outlet
 * asal (outlet null = satu per tenant untuk lokasi tanpa outlet). Dipakai yang sudah ada (lokasi aktif berjenis
 * DalamPerjalanan di outlet itu, Id terkecil); bila belum ada, dibuat dengan kode `TRANSIT[-n]` unik per tenant.
 * Lokasi ini bukan lokasi stok jual (BR-02.4 tidak terpengaruh) dan nilainya dijurnal ke Persediaan Dalam Perjalanan.
 * Dipanggil di dalam transaksi pemanggil (savepoint); audit `gudang.buat` bila dibuat.
 */
final class SiapkanGudangDalamPerjalanan
{
    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?int $idOutlet): DataInfoGudang
    {
        return DB::transaction(function () use ($idOutlet): DataInfoGudang {
            $kueri = fn () => Gudang::query()
                ->where('Jenis', JenisGudang::DalamPerjalanan->value)
                ->where('Status', StatusOrganisasi::Aktif->value)
                ->when($idOutlet === null, fn ($k) => $k->whereNull('IdOutlet'), fn ($k) => $k->where('IdOutlet', $idOutlet))
                ->orderBy('Id');

            $ada = $kueri()->first();

            if ($ada === null) {
                // Kunci outlet (atau baris gudang tenant) supaya dua pengiriman bersamaan tidak membuat dua lokasi.
                if ($idOutlet !== null) {
                    Outlet::query()->whereKey($idOutlet)->lockForUpdate()->first();
                }

                $ada = $kueri()->lockForUpdate()->first() ?? $this->Buat($idOutlet);
            }

            return $this->infoGudang->AmbilBanyak([$ada->Id])[$ada->Id];
        });
    }

    private function Buat(?int $idOutlet): Gudang
    {
        $kode = 'TRANSIT';
        $urut = 1;

        while (Gudang::query()->where('Kode', $kode)->exists()) {
            $urut++;
            $kode = 'TRANSIT-'.$urut;
        }

        $gudang = Gudang::query()->create([
            'IdOutlet' => $idOutlet,
            'Kode' => $kode,
            'Nama' => 'Dalam perjalanan',
            'Jenis' => JenisGudang::DalamPerjalanan,
        ]);
        $this->audit->Catat('gudang.buat', $gudang, nilaiBaru: $gudang->only(['IdOutlet', 'Kode', 'Nama', 'Jenis']));

        return $gudang;
    }
}
