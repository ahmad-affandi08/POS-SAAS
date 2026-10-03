<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * F-18 bagian 4 (D-37): wajibkan kode layar QR untuk absen web di outlet. Hanya bisa dinyalakan bila layar QR sudah
 * dibuat; absen yang tidak menyertakan kode yang berlaku ditolak.
 */
final class AturWajibQrAbsensi
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Outlet $outlet, bool $wajib): Outlet
    {
        return DB::transaction(function () use ($outlet, $wajib): Outlet {
            $baris = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);

            if ($wajib && $baris->HashTokenLayarAbsen === null) {
                throw new PelanggaranAturanBisnis('LayarQrBelumAda', 'Buat tautan layar QR dulu, pasang di outlet, baru wajibkan QR.', 'WajibQrAbsensi');
            }

            if ($baris->WajibQrAbsensi !== $wajib) {
                $baris->forceFill(['WajibQrAbsensi' => $wajib])->save();
                $this->audit->Catat('outlet.wajib-qr-absensi.ubah', $baris, ['WajibQrAbsensi' => ! $wajib], ['WajibQrAbsensi' => $wajib]);
            }

            return $baris;
        });
    }
}
