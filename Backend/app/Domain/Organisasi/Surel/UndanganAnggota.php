<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use App\Domain\Organisasi\Model\UndanganPengguna;
use Illuminate\Mail\Mailable;

/**
 * Email undangan anggota tenant (F-02 langkah 3). Template final dikelola lewat P-06.
 */
final class UndanganAnggota extends Mailable
{
    public function __construct(
        public readonly UndanganPengguna $undangan,
        private readonly string $token,
        public readonly string $namaPengundang,
        public readonly string $namaTenant,
    ) {
        $this->subject("Undangan bergabung ke {$namaTenant}")->text('Surel.Tenant.UndanganAnggota', [
            'Tautan' => $this->Tautan(),
            'NamaPengundang' => $namaPengundang,
            'NamaTenant' => $namaTenant,
            'BerlakuSampai' => $undangan->BerlakuSampai->timezone('Asia/Jakarta')->translatedFormat('d F Y H.i').' WIB',
        ]);
    }

    /** Tautan asli hanya untuk test & pengiriman; tidak pernah disimpan. */
    public function Tautan(): string
    {
        return route('undangan.tampil', ['token' => $this->token]);
    }
}
