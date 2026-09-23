<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Aksi;

use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\PembayaranLangganan;
use Illuminate\Support\Facades\Storage;

/**
 * Membuka bukti transfer tenant dari disk privat untuk verifikasi (P-08). Setiap pembukaan tercatat di audit
 * pengelola beserta tenantnya, karena berkas ini data tenant yang dibuka lintas tenant.
 */
final class BukaBuktiPembayaran
{
    public function __construct(
        private readonly DaftarTagihanPlatform $daftar,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    /** Null bila pembayaran atau berkasnya tidak ada. */
    public function Jalankan(PenggunaPengelola $pelaku, string $uuidPembayaran): ?PembayaranLangganan
    {
        $pembayaran = $this->daftar->CariPembayaran($uuidPembayaran);

        if ($pembayaran === null || $pembayaran->PathBukti === null
            || ! Storage::disk((string) config('tagihan.DiskBukti'))->exists($pembayaran->PathBukti)) {
            return null;
        }

        $this->audit->Catat('tagihan.bukti.lihat', $pembayaran, idPelaku: $pelaku->Id, idTenant: $pembayaran->IdTenant);

        return $pembayaran;
    }
}
