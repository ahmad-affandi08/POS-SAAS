<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Layanan\PemeriksaCaptcha;
use App\Domain\Organisasi\Aksi\KirimVerifikasiEmail;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Kueri\PaketTersedia;
use App\Domain\Tenant\Kueri\StatusPendaftaran;
use App\Domain\Tenant\Model\Paket;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\IdentifikasiTenantSesi;
use App\Http\Permintaan\Autentikasi\DaftarPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Registrasi tenant (F-00 langkah 1–4, BR-00.4, BR-00.5, BR-P06.2).
 */
final class PendaftaranKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan, StatusPendaftaran $status, PaketTersedia $paket, PemeriksaCaptcha $captcha): Response
    {
        $daftarPaket = array_values(array_map(
            fn (Paket $baris) => ['Kode' => $baris->Kode, 'Nama' => $baris->Nama, 'MasaTrialHari' => $baris->MasaTrialHari],
            array_filter($paket->AmbilUntukPendaftaran(), fn (Paket $baris) => ! $baris->HargaNegosiasi),
        ));
        $kodeTersedia = array_column($daftarPaket, 'Kode');
        $diminta = mb_strtoupper($permintaan->string('paket')->toString());

        return Inertia::render('Autentikasi/Daftar', [
            'Dibuka' => $status->AmbilAlasanDitutup() === [],
            'Paket' => $daftarPaket,
            // BR-00.6: kode yang tidak tersedia (salah ketik, negosiasi) jatuh ke paket bawaan, bukan paket pertama.
            'PaketTerpilih' => in_array($diminta, $kodeTersedia, true) ? $diminta : (string) config('tenant.KodePaketTrialBawaan'),
            'KunciSitusCaptcha' => $captcha->CekAktif() ? (string) config('integrasi.Turnstile.KunciSitus') : null,
        ]);
    }

    public function Daftar(
        DaftarPermintaan $permintaan,
        StatusPendaftaran $status,
        PemeriksaCaptcha $captcha,
        DaftarkanTenant $daftarkan,
        KirimVerifikasiEmail $kirimVerifikasi,
    ): RedirectResponse {
        $alasanDitutup = $status->AmbilAlasanDitutup();

        if ($alasanDitutup !== []) {
            Log::warning('Pendaftaran ditolak karena prasyarat belum lengkap.', ['Alasan' => $alasanDitutup]);

            throw new PelanggaranAturanBisnis('BR-P06.2', 'Pendaftaran belum dibuka. Silakan coba lagi nanti.');
        }

        if (! $captcha->Periksa($permintaan->string('TokenCaptcha')->toString(), $permintaan->ip())) {
            throw new PelanggaranAturanBisnis('BR-00.4', 'Verifikasi CAPTCHA gagal. Selesaikan CAPTCHA sekali lagi.', 'TokenCaptcha');
        }

        $hasil = $daftarkan->Jalankan($permintaan->AmbilData());

        Auth::guard('web')->login($hasil['Pengguna']);
        $permintaan->session()->regenerate();
        $permintaan->session()->put(IdentifikasiTenantSesi::KUNCI_SESI, $hasil['Tenant']->Id);

        try {
            $kirimVerifikasi->Jalankan($hasil['Pengguna']);
        } catch (Throwable $galat) {
            // Tenant sudah terbentuk; email bisa dikirim ulang dari banner (BR-00.5).
            Log::error('Email verifikasi gagal dikirim.', ['Pesan' => $galat->getMessage()]);
        }

        return redirect()->route('kelola.panduan-awal')
            ->with('Kilat', "Selamat datang di {$hasil['Tenant']->Nama}! Cek email Anda untuk verifikasi.");
    }
}
