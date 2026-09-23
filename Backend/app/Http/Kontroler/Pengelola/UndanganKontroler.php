<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Aksi\TerimaUndangan;
use App\Domain\Pengelola\TimInternal\Kueri\UndanganBerlaku;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\Pengelola\SesiPengelola;
use App\Http\Permintaan\Pengelola\TerimaUndanganPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anggota baru menerima undangan dan membuat kata sandi (P-01 langkah 3–4).
 */
final class UndanganKontroler extends Kontroler
{
    public function Tampilkan(string $token, UndanganBerlaku $undanganBerlaku): Response
    {
        $undangan = $undanganBerlaku->Cari($token);

        return Inertia::render('Pengelola/Undangan/Terima', [
            'Token' => $token,
            'Berlaku' => $undangan !== null,
            'Email' => $undangan?->Email,
        ]);
    }

    public function Terima(string $token, TerimaUndanganPermintaan $permintaan, TerimaUndangan $terima): RedirectResponse
    {
        $pengguna = $terima->Jalankan(
            $token,
            $permintaan->string('Nama')->toString(),
            $permintaan->string('KataSandi')->toString(),
        );

        Auth::guard(SesiPengelola::GUARD)->login($pengguna);
        $sesi = $permintaan->session();
        $sesi->regenerate();
        $sesi->put(SesiPengelola::TERAKHIR_AKTIF, now()->getTimestamp());

        return redirect()->route('pengelola.dua-faktor.aktifkan');
    }
}
