<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use Closure;
use Illuminate\Contracts\Auth\Factory as PabrikAutentikasi;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * Konteks tugas antrean impor (F-03, CLAUDE.md #11): tugas membawa `IdTenant`, lalu menetapkan `KonteksTenant`
 * sebelum menyentuh data (scope `MilikTenant` membatasi semua kueri ke tenant itu). Pengunggah impor dijadikan
 * pelaku audit & pengubah `RiwayatHarga` bila tugas berjalan di luar request (worker antrean). Konteks sebelumnya
 * dipulihkan setelah selesai.
 */
final class KonteksTugasImpor
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PencatatAudit $audit,
        private readonly PabrikAutentikasi $autentikasi,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $kerja
     * @return T
     */
    public function Jalankan(int $idTenant, int $idPengguna, Closure $kerja): mixed
    {
        $tenantSebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($idTenant);
        $guard = $this->autentikasi->guard('web');
        $pelakuDiatur = false;

        if ($guard instanceof StatefulGuard && $guard->guest()) {
            $guard->onceUsingId($idPengguna);
            $this->audit->AturKonteks($idPengguna, null, 'Antrean impor produk');
            $pelakuDiatur = true;
        }

        try {
            return $kerja();
        } finally {
            if ($pelakuDiatur) {
                if (method_exists($guard, 'forgetUser')) {
                    $guard->forgetUser();
                }

                $this->audit->AturKonteks(null, null, null);
            }

            $tenantSebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($tenantSebelumnya);
        }
    }
}
