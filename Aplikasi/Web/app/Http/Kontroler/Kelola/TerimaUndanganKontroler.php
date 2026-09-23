<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\TerimaUndanganAnggota;
use App\Domain\Organisasi\Kueri\UndanganBerlaku;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Permintaan\Kelola\TerimaUndanganPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Penerima membuka tautan undangan anggota (F-02 langkah 3, BR-00.1). Dapat dibuka tanpa masuk: akun baru dibuat
 * di halaman ini, akun yang sudah ada diminta masuk dulu lalu kembali ke tautan yang sama.
 */
final class TerimaUndanganKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan, string $token, UndanganBerlaku $undanganBerlaku, RingkasanTenant $ringkasan): Response
    {
        $undangan = $undanganBerlaku->Cari($token);
        $masuk = $permintaan->user('web');

        if ($undangan !== null && ! $masuk instanceof Pengguna) {
            // Setelah masuk, pengguna dikembalikan ke tautan ini (redirect()->intended di SesiKontroler).
            $permintaan->session()->put('url.intended', $permintaan->fullUrl());
        }

        return Inertia::render('Undangan/Terima', [
            'Token' => $token,
            'Berlaku' => $undangan !== null,
            'Email' => $undangan?->Email,
            'NamaTenant' => $undangan === null ? null : ($ringkasan->Ambil([$undangan->IdTenant])[0]['Nama'] ?? null),
            'AkunAda' => $undangan !== null && Pengguna::query()->where('Email', $undangan->Email)->exists(),
            'EmailMasuk' => $masuk instanceof Pengguna ? $masuk->Email : null,
        ]);
    }

    public function Terima(string $token, TerimaUndanganPermintaan $permintaan, TerimaUndanganAnggota $terima): RedirectResponse
    {
        $masuk = $permintaan->user('web');
        $hasil = $terima->Jalankan($token, $masuk instanceof Pengguna ? $masuk : null, $permintaan->AmbilAkunBaru());

        if (! $masuk instanceof Pengguna) {
            Auth::guard('web')->login($hasil['Pengguna']);
            $permintaan->session()->regenerate();
        }

        $permintaan->session()->put(IdentifikasiTenantSesi::KUNCI_SESI, $hasil['IdTenant']);

        return redirect()->route('kelola.beranda')->with('Kilat', 'Undangan diterima. Selamat bergabung.');
    }
}
