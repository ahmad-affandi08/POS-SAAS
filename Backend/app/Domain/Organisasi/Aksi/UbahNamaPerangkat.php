<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Perangkat;
use Illuminate\Support\Facades\DB;

/**
 * F-02b: ubah nama tampilan perangkat (misal "Kasir Depan"). Kode perangkat tidak pernah berubah.
 */
final class UbahNamaPerangkat
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Perangkat $perangkat, string $nama): Perangkat
    {
        return DB::transaction(function () use ($perangkat, $nama): Perangkat {
            $perangkat = Perangkat::query()->lockForUpdate()->findOrFail($perangkat->Id);

            if ($perangkat->CekDicabut()) {
                throw new PelanggaranAturanBisnis('PerangkatDicabut', 'Perangkat yang sudah dicabut tidak bisa diubah.', 'Nama');
            }

            $lama = $perangkat->Nama;
            $perangkat->Nama = trim($nama);

            if ($perangkat->isDirty('Nama')) {
                $perangkat->save();
                $this->audit->Catat('perangkat.ubah', $perangkat, nilaiLama: ['Nama' => $lama], nilaiBaru: ['Nama' => $perangkat->Nama]);
            }

            return $perangkat;
        });
    }
}
