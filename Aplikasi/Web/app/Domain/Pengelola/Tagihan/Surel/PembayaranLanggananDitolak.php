<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/**
 * Pemberitahuan ke Owner: bukti transfer ditolak beserta alasannya; tagihan tetap bisa dibayar (P-08 langkah 3).
 */
final class PembayaranLanggananDitolak extends SurelDasar
{
    public function __construct(
        public readonly string $nama,
        public readonly string $nomorTagihan,
        public readonly string $total,
        public readonly string $alasan,
    ) {
        $this->subject("Bukti transfer tagihan {$nomorTagihan} perlu diperbaiki")
            ->IsiSurel('Tenant.PembayaranLanggananDitolak', [
                'Nama' => $nama,
                'NomorTagihan' => $nomorTagihan,
                'Total' => $total,
                'Alasan' => $alasan,
                // Dikirim dari subdomain pengelola: tautan wajib memakai domain aplikasi tenant, bukan host request.
                'Tautan' => rtrim((string) config('app.url'), '/').'/kelola/langganan',
            ]);
    }
}
