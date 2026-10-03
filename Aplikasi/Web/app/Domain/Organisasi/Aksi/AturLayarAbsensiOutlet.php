<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Layanan\KodeLayarAbsensi;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F-18 bagian 4 (D-37): buat (ulang) atau cabut tautan layar QR absensi outlet. Buat ulang mematikan tautan & kode
 * lama seketika. Cabut sekaligus mematikan kewajiban QR (tidak ada kode yang bisa dibuktikan tanpa layar).
 */
final class AturLayarAbsensiOutlet
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /** @return string|null token baru, atau null bila dicabut */
    public function Jalankan(Outlet $outlet, bool $buat): ?string
    {
        $token = $buat ? Str::random(KodeLayarAbsensi::PANJANG_TOKEN) : null;

        DB::transaction(function () use ($outlet, $token, $buat): void {
            $baris = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);
            $baris->forceFill([
                'TokenLayarAbsen' => $token,
                'HashTokenLayarAbsen' => $token === null ? null : KodeLayarAbsensi::Hash($token),
                ...($buat ? [] : ['WajibQrAbsensi' => false]),
            ])->save();
            $this->audit->Catat($buat ? 'outlet.layar-absensi.buat' : 'outlet.layar-absensi.cabut', $baris, null, ['Nama' => $baris->Nama]);
        });

        return $token;
    }
}
