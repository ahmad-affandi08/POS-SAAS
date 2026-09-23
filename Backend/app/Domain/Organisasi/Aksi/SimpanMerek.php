<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Merek;
use Illuminate\Support\Facades\DB;

/**
 * Tambah atau ganti nama merek (F-02: Tenant → Merek → Outlet). Nama merek unik per tenant.
 */
final class SimpanMerek
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(?Merek $merek, string $nama): Merek
    {
        return DB::transaction(function () use ($merek, $nama): Merek {
            $nama = trim($nama);
            $kembar = Merek::query()->where('Nama', $nama)->when($merek !== null, fn ($kueri) => $kueri->whereKeyNot($merek?->Id))->exists();

            if ($kembar) {
                throw new PelanggaranAturanBisnis('MerekKembar', "Merek {$nama} sudah ada.", 'Nama');
            }

            if ($merek === null) {
                $merek = Merek::query()->create(['Nama' => $nama]);
                $this->audit->Catat('merek.buat', $merek, nilaiBaru: ['Nama' => $nama]);

                return $merek;
            }

            $lama = $merek->Nama;

            if ($lama !== $nama) {
                $merek->update(['Nama' => $nama]);
                $this->audit->Catat('merek.ubah', $merek, nilaiLama: ['Nama' => $lama], nilaiBaru: ['Nama' => $nama]);
            }

            return $merek;
        });
    }
}
