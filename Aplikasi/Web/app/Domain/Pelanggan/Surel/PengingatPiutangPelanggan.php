<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Surel;

use Illuminate\Mail\Mailable;

/** Email pengingat piutang ke pelanggan (D-23 D bagian 4b); isi sama dengan teks WhatsApp. */
final class PengingatPiutangPelanggan extends Mailable
{
    public function __construct(
        public readonly string $namaUsaha,
        public readonly string $nomor,
        public readonly string $isi,
    ) {
        $this->subject("Pengingat tagihan {$nomor} dari {$namaUsaha}")
            ->text('Surel.Tenant.PengingatPiutangPelanggan', ['Isi' => $isi, 'NamaUsaha' => $namaUsaha]);
    }
}
