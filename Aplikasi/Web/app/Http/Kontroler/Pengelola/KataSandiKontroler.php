<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Domain\Pengelola\TimInternal\Aksi\GantiKataSandiPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\Pengelola\SesiPengelola;
use App\Http\Permintaan\Pengelola\GantiKataSandiPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D-22: halaman ganti kata sandi anggota tim. Setelah kata sandi awal diganti, alur lanjut ke aktivasi 2FA.
 */
final class KataSandiKontroler extends Kontroler
{
    public function Tampilkan(): Response
    {
        return Inertia::render('Pengelola/GantiKataSandi', ['Wajib' => $this->Pelaku()->WajibGantiKataSandi]);
    }

    public function Simpan(GantiKataSandiPermintaan $permintaan, GantiKataSandiPengelola $ganti): RedirectResponse
    {
        $pengguna = $this->Pelaku();
        $ganti->Jalankan(
            $pengguna,
            $permintaan->string('KataSandiLama')->toString(),
            $permintaan->string('KataSandi')->toString(),
        );
        $permintaan->session()->regenerate();

        return redirect()->route($pengguna->CekDuaFaktorAktif() ? 'pengelola.beranda' : 'pengelola.dua-faktor.aktifkan')
            ->with('Kilat', 'Kata sandi diganti.');
    }

    private function Pelaku(): PenggunaPengelola
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();
        abort_unless($pengguna instanceof PenggunaPengelola, 403);

        return $pengguna;
    }
}
