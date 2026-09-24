<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan atau menonaktifkan daftar harga. Daftar harga tidak pernah dihapus (riwayat harga tetap utuh,
 * DesainF03 H.15); daftar nonaktif diabaikan `PenentuHarga`. Status yang sama = tanpa tulis. Audit
 * `daftar-harga.aktifkan` / `daftar-harga.nonaktifkan`.
 */
final class UbahStatusDaftarHarga
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DaftarHarga $daftar, bool $aktif): DaftarHarga
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $daftar, $aktif): DaftarHarga {
            $this->penguncian->Kunci($idTenant);
            $daftar = DaftarHarga::query()->whereKey($daftar->Id)->lockForUpdate()->firstOrFail();

            if ($daftar->Aktif === $aktif) {
                return $daftar;
            }

            $daftar->fill(['Aktif' => $aktif])->save();
            $this->audit->Catat($aktif ? 'daftar-harga.aktifkan' : 'daftar-harga.nonaktifkan', $daftar, ['Aktif' => ! $aktif], ['Aktif' => $aktif]);

            return $daftar;
        });
    }
}
