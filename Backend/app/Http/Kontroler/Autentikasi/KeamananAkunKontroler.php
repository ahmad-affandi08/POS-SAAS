<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Aksi\AktifkanDuaFaktorPengguna;
use App\Domain\Organisasi\Aksi\NonaktifkanDuaFaktorPengguna;
use App\Domain\Organisasi\Layanan\DuaFaktorPengguna;
use App\Domain\Organisasi\Layanan\PenentuWajibDuaFaktor;
use App\Domain\Organisasi\Model\Pengguna;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\SesiAutentikasiTenant;
use App\Http\Permintaan\Autentikasi\KodeDuaFaktorPermintaan;
use App\Http\Permintaan\Autentikasi\NonaktifkanDuaFaktorPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Keamanan akun di back-office (`/kelola/keamanan`): aktivasi 2FA TOTP (QR + konfirmasi kode), kode pemulihan yang
 * ditampilkan sekali, dan nonaktifkan dengan konfirmasi kata sandi (§20.2, BR-00.8). 2FA milik akun pengguna, bukan
 * tenant; tenant aktif hanya menentukan apakah 2FA wajib.
 */
final class KeamananAkunKontroler extends Kontroler
{
    private const MAKS_PERCOBAAN = 5;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenentuWajibDuaFaktor $penentuWajib,
    ) {}

    public function Tampilkan(Request $permintaan, DuaFaktorPengguna $duaFaktor): Response
    {
        $pengguna = $this->PenggunaMasuk($permintaan);
        $sesi = $permintaan->session();
        $aktivasi = null;

        if (! $pengguna->CekDuaFaktorAktif()) {
            $rahasia = $sesi->get(SesiAutentikasiTenant::RAHASIA_2FA_SEMENTARA);

            if (! is_string($rahasia)) {
                $rahasia = $duaFaktor->BuatRahasia();
                $sesi->put(SesiAutentikasiTenant::RAHASIA_2FA_SEMENTARA, $rahasia);
            }

            $aktivasi = [
                'QrSvg' => $duaFaktor->BuatQrSvg($duaFaktor->BuatUrlOtp($pengguna->Email, $rahasia)),
                'Rahasia' => trim(chunk_split($rahasia, 4, ' ')),
            ];
        }

        $kodeBaru = $sesi->get(SesiAutentikasiTenant::KODE_PEMULIHAN_BARU);

        return Inertia::render('Autentikasi/KeamananAkun', [
            'DuaFaktor' => [
                'Aktif' => $pengguna->CekDuaFaktorAktif(),
                'AktifPada' => $pengguna->DuaFaktorAktifPada?->toIso8601String(),
                'SisaKodePemulihan' => count($pengguna->KodePemulihan2fa ?? []),
                'Wajib' => $this->CekWajib($pengguna),
            ],
            'Aktivasi' => $aktivasi,
            'KodePemulihanBaru' => is_array($kodeBaru) ? array_values($kodeBaru) : null,
        ]);
    }

    public function AktifkanDuaFaktor(KodeDuaFaktorPermintaan $permintaan, AktifkanDuaFaktorPengguna $aktifkan): RedirectResponse
    {
        $pengguna = $this->PenggunaMasuk($permintaan);
        $this->BatasiPercobaan('keamanan-2fa-aktifkan:'.$pengguna->Id);
        $rahasia = $permintaan->session()->get(SesiAutentikasiTenant::RAHASIA_2FA_SEMENTARA);

        if (! is_string($rahasia)) {
            return redirect()->route('kelola.keamanan');
        }

        $kodePemulihan = $aktifkan->Jalankan($pengguna, $rahasia, $permintaan->string('Kode')->toString());

        $sesi = $permintaan->session();
        $sesi->forget(SesiAutentikasiTenant::RAHASIA_2FA_SEMENTARA);
        $sesi->migrate(true);
        $sesi->flash(SesiAutentikasiTenant::KODE_PEMULIHAN_BARU, $kodePemulihan);

        return redirect()->route('kelola.keamanan')->with('Kilat', 'Verifikasi dua langkah aktif. Simpan kode pemulihan di bawah.');
    }

    public function NonaktifkanDuaFaktor(NonaktifkanDuaFaktorPermintaan $permintaan, NonaktifkanDuaFaktorPengguna $nonaktifkan): RedirectResponse
    {
        $pengguna = $this->PenggunaMasuk($permintaan);

        if ($this->CekWajib($pengguna)) {
            throw new PelanggaranAturanBisnis('BR-00.8', 'Paket langganan usaha ini mewajibkan verifikasi dua langkah untuk Owner, jadi tidak bisa dinonaktifkan.');
        }

        $this->BatasiPercobaan('keamanan-2fa-nonaktifkan:'.$pengguna->Id, 'KataSandi');
        $nonaktifkan->Jalankan($pengguna, $permintaan->string('KataSandi')->toString());

        return redirect()->route('kelola.keamanan')->with('Kilat', 'Verifikasi dua langkah dinonaktifkan.');
    }

    private function PenggunaMasuk(Request $permintaan): Pengguna
    {
        $pengguna = $permintaan->user('web');
        abort_unless($pengguna instanceof Pengguna, 403);

        return $pengguna;
    }

    private function CekWajib(Pengguna $pengguna): bool
    {
        $idTenant = $this->konteks->Ambil();

        return $idTenant !== null && $this->penentuWajib->CekWajib($pengguna->Id, $idTenant);
    }

    private function BatasiPercobaan(string $kunci, string $bidang = 'Kode'): void
    {
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            throw new PelanggaranAturanBisnis('TerlaluBanyakPercobaan', 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.', $bidang);
        }

        RateLimiter::hit($kunci);
    }
}
