<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Model\Pengguna;

/**
 * Peristiwa keamanan akun (2FA, atur ulang kata sandi) milik akun pengguna, bukan satu tenant. `LogAudit` selalu
 * bertenant (§25 no. 17), sehingga peristiwa dicatat di setiap tenant tempat pengguna menjadi anggota aktif: Owner
 * dan Admin setiap tenant itu melihatnya di log audit masing-masing. Pelaku = pengguna itu sendiri.
 */
final class PencatatAuditAkun
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly KeanggotaanPengguna $keanggotaan,
    ) {}

    /**
     * @param  array<string, mixed>|null  $nilaiBaru
     */
    public function Catat(string $peristiwa, Pengguna $pengguna, ?array $nilaiBaru = null): void
    {
        foreach ($this->keanggotaan->AmbilIdTenant($pengguna->Id) as $idTenant) {
            $this->audit->Catat($peristiwa, $pengguna, nilaiBaru: $nilaiBaru, idTenant: $idTenant, idPengguna: $pengguna->Id);
        }
    }
}
