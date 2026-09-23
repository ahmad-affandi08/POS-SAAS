<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Surel;

use Illuminate\Mail\Mailable;

/**
 * Balasan tim Dukungan ke pelapor tiket (P-09). Dikirim lewat antrean setelah balasan tersimpan. Catatan internal
 * tidak pernah dikirim.
 */
final class BalasanTiketDukungan extends Mailable
{
    public function __construct(
        public readonly string $nomor,
        public readonly string $judul,
        public readonly string $uuidTiket,
        public readonly string $namaPetugas,
        public readonly string $isi,
        public readonly string $status,
    ) {
        $this->subject("Balasan tiket {$nomor}: {$judul}")
            ->text('Surel.Tenant.BalasanTiketDukungan', [
                'Nomor' => $nomor,
                'Judul' => $judul,
                'NamaPetugas' => $namaPetugas,
                'Isi' => $isi,
                'Status' => $status,
                'Tautan' => rtrim((string) config('app.url'), '/')."/kelola/bantuan/{$uuidTiket}",
            ]);
    }
}
