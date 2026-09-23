<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Permintaan\Autentikasi\MasukPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Masuk, keluar, dan pemilih tenant back-office (BR-00.1). Percobaan masuk dibatasi 5 kali per menit per email+IP.
 */
final class SesiKontroler extends Kontroler
{
    public const BATAS_PERCOBAAN_MASUK = 5;

    public function TampilkanMasuk(): Response
    {
        return Inertia::render('Autentikasi/Masuk');
    }

    public function Masuk(MasukPermintaan $permintaan, KeanggotaanPengguna $keanggotaan): RedirectResponse
    {
        $email = mb_strtolower($permintaan->string('Email')->toString());
        $kunci = 'masuk:'.$email.'|'.$permintaan->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PERCOBAAN_MASUK)) {
            $detik = RateLimiter::availableIn($kunci);

            throw new PelanggaranAturanBisnis('TerlaluBanyakPercobaan', "Terlalu banyak percobaan. Coba lagi dalam {$detik} detik.", 'Email');
        }

        $berhasil = Auth::guard('web')->attempt(
            ['Email' => $email, 'password' => $permintaan->string('KataSandi')->toString()],
            $permintaan->boolean('Ingat'),
        );

        if (! $berhasil) {
            RateLimiter::hit($kunci, 60);

            throw new PelanggaranAturanBisnis('KredensialSalah', 'Email atau kata sandi salah.', 'Email');
        }

        RateLimiter::clear($kunci);
        $permintaan->session()->regenerate();

        $pengguna = Auth::guard('web')->user();
        $daftarTenant = $pengguna instanceof Pengguna ? $keanggotaan->AmbilIdTenant($pengguna->Id) : [];

        if (count($daftarTenant) === 1) {
            $permintaan->session()->put(IdentifikasiTenantSesi::KUNCI_SESI, $daftarTenant[0]);

            return redirect()->intended(route('kelola.beranda'));
        }

        return redirect()->route('pilih-tenant');
    }

    public function TampilkanPilihTenant(Request $permintaan, KeanggotaanPengguna $keanggotaan, RingkasanTenant $ringkasan): Response
    {
        $pengguna = $permintaan->user('web');
        $daftar = $pengguna instanceof Pengguna ? $ringkasan->Ambil($keanggotaan->AmbilIdTenant($pengguna->Id)) : [];

        return Inertia::render('Autentikasi/PilihTenant', [
            'Tenant' => array_map(fn (array $baris) => ['Uuid' => $baris['Uuid'], 'Nama' => $baris['Nama']], $daftar),
        ]);
    }

    public function PilihTenant(Request $permintaan, KeanggotaanPengguna $keanggotaan, RingkasanTenant $ringkasan): RedirectResponse
    {
        $pengguna = $permintaan->user('web');
        $uuid = $permintaan->string('Tenant')->toString();
        $daftar = $pengguna instanceof Pengguna ? $ringkasan->Ambil($keanggotaan->AmbilIdTenant($pengguna->Id)) : [];
        $terpilih = array_values(array_filter($daftar, fn (array $baris) => $baris['Uuid'] === $uuid))[0] ?? null;

        // Tenant yang bukan milik pengguna diperlakukan sama dengan yang tidak ada (isolasi tenant).
        abort_if($terpilih === null, 404);

        $permintaan->session()->put(IdentifikasiTenantSesi::KUNCI_SESI, $terpilih['Id']);

        return redirect()->route('kelola.beranda');
    }

    public function Keluar(Request $permintaan): RedirectResponse
    {
        Auth::guard('web')->logout();
        $permintaan->session()->invalidate();
        $permintaan->session()->regenerateToken();

        return redirect()->route('masuk');
    }
}
