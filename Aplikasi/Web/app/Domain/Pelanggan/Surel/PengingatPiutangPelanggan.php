<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Surel;

use App\Domain\Bersama\Surel\SurelDasar;

/** Email pengingat piutang ke pelanggan (D-23 D bagian 4b); isi sama dengan teks WhatsApp. */
final class PengingatPiutangPelanggan extends SurelDasar
{
    public function __construct(
        public readonly string $namaUsaha,
        public readonly string $nomor,
        public readonly string $isi,
    ) {
        $this->subject("Pengingat tagihan {$nomor} dari {$namaUsaha}")
            ->IsiSurel('Tenant.PengingatPiutangPelanggan', ['Isi' => $isi, 'NamaUsaha' => $namaUsaha]);
    }
}
