<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\UndanganPengguna;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan undangan yang belum diterima (F-02). Kursi pengguna yang dijanjikan undangan itu kembali bebas.
 */
final class BatalkanUndangan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(UndanganPengguna $undangan): void
    {
        DB::transaction(function () use ($undangan): void {
            $undangan = UndanganPengguna::query()->lockForUpdate()->findOrFail($undangan->Id);

            if ($undangan->DiterimaPada !== null || $undangan->DibatalkanPada !== null) {
                throw new PelanggaranAturanBisnis('UndanganSelesai', 'Undangan ini sudah diterima atau dibatalkan.');
            }

            $undangan->update(['DibatalkanPada' => now()]);
            $this->audit->Catat('pengguna.undangan-batal', $undangan, nilaiBaru: ['Email' => $undangan->Email]);
        });
    }
}
