<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Layanan\PenandaVerifikasiEmail;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Surel\VerifikasiEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Mengirim tautan verifikasi email bertanda tangan (BR-00.5). Tautan memuat hash email sehingga tidak berlaku lagi
 * bila email diganti.
 */
final class KirimVerifikasiEmail
{
    public function __construct(private readonly PenandaVerifikasiEmail $penanda) {}

    public function Jalankan(Pengguna $pengguna): void
    {
        if ($pengguna->EmailDiverifikasiPada !== null) {
            return;
        }

        $jam = (int) config('tenant.JamBerlakuVerifikasiEmail');
        $tautan = URL::temporarySignedRoute('verifikasi-email', now()->addHours($jam), [
            'pengguna' => $pengguna->Uuid,
            'hash' => $this->penanda->BuatHash($pengguna),
        ]);

        Mail::to($pengguna->Email)->send(new VerifikasiEmail($pengguna->Nama, $tautan, $jam));
    }
}
