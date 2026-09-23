<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Surel;

use Illuminate\Mail\Mailable;

/**
 * Pemberitahuan ke Owner: bukti transfer diterima, tagihan lunas, langganan aktif (P-08 langkah 3).
 */
final class PembayaranLanggananDiterima extends Mailable
{
    public function __construct(
        public readonly string $nama,
        public readonly string $nomorTagihan,
        public readonly string $total,
        public readonly string $namaPaket,
        public readonly string $periodeSelesai,
    ) {
        $this->subject("Pembayaran tagihan {$nomorTagihan} diterima")
            ->text('Surel.Tenant.PembayaranLanggananDiterima', [
                'Nama' => $nama,
                'NomorTagihan' => $nomorTagihan,
                'Total' => $total,
                'NamaPaket' => $namaPaket,
                'PeriodeSelesai' => $periodeSelesai,
                // Dikirim dari subdomain pengelola: tautan wajib memakai domain aplikasi tenant, bukan host request.
                'Tautan' => rtrim((string) config('app.url'), '/').'/kelola/langganan',
            ]);
    }
}
