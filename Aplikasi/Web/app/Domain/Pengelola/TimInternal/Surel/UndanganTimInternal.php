<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Surel;

use App\Domain\Pengelola\TimInternal\Model\UndanganPengelola;
use Illuminate\Mail\Mailable;

/**
 * Email undangan anggota tim internal (P-01 langkah 3). Template final dikelola lewat P-06.
 */
final class UndanganTimInternal extends Mailable
{
    public function __construct(
        public readonly UndanganPengelola $undangan,
        private readonly string $token,
        public readonly string $namaPengundang,
    ) {
        $this->subject('Undangan bergabung ke Platform Pengelola')->text('Surel.Pengelola.UndanganTimInternal', [
            'Tautan' => $this->Tautan(),
            'NamaPengundang' => $namaPengundang,
            'BerlakuSampai' => $undangan->BerlakuSampai->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB',
        ]);
    }

    /** Tautan asli hanya untuk test & pengiriman; tidak pernah disimpan. */
    public function Tautan(): string
    {
        return route('pengelola.undangan.tampil', ['token' => $this->token]);
    }
}
