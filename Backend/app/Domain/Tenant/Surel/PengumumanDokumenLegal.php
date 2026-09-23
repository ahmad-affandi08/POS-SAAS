<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Surel;

use Illuminate\Mail\Mailable;

/**
 * Pengumuman versi materiil dokumen legal ke Owner, paling lambat 30 hari sebelum berlaku (P-06, BR-P06.5).
 */
final class PengumumanDokumenLegal extends Mailable
{
    public function __construct(
        public readonly string $nama,
        public readonly string $labelDokumen,
        public readonly int $versi,
        public readonly string $berlakuMulai,
        public readonly ?string $ringkasanPerubahan,
        public readonly string $tautan,
    ) {
        $this->subject("Perubahan {$labelDokumen} berlaku mulai {$berlakuMulai}")
            ->text('Surel.Tenant.PengumumanDokumenLegal', [
                'Nama' => $nama,
                'LabelDokumen' => $labelDokumen,
                'Versi' => $versi,
                'BerlakuMulai' => $berlakuMulai,
                'RingkasanPerubahan' => $ringkasanPerubahan,
                'Tautan' => $tautan,
            ]);
    }
}
