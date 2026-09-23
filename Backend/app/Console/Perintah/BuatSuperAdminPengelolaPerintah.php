<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Aksi\BuatSuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * P-01 langkah 1: Super Admin dibuat dari server, tidak ada halaman pendaftaran publik untuk pengelola.
 * Kata sandi diminta secara tersembunyi dan tidak pernah dicetak atau dicatat ke log.
 */
final class BuatSuperAdminPengelolaPerintah extends Command
{
    protected $signature = 'pengelola:buat-super-admin
        {--nama= : Nama lengkap}
        {--email= : Email untuk masuk Platform Pengelola}';

    protected $description = 'Membuat akun Super Admin Platform Pengelola (P-01).';

    public function handle(BuatSuperAdmin $buatSuperAdmin): int
    {
        $nama = $this->option('nama');
        $nama = is_string($nama) && $nama !== '' ? $nama : (string) $this->ask('Nama lengkap');
        $email = $this->option('email');
        $email = Str::lower(trim(is_string($email) && $email !== '' ? $email : (string) $this->ask('Email')));
        $kataSandi = (string) $this->secret('Kata sandi (minimal 12 karakter, huruf dan angka)');
        $konfirmasi = (string) $this->secret('Ulangi kata sandi');

        $validasi = Validator::make(
            ['Nama' => $nama, 'Email' => $email, 'KataSandi' => $kataSandi, 'KonfirmasiKataSandi' => $konfirmasi],
            [
                'Nama' => ['required', 'string', 'max:150'],
                'Email' => ['required', 'email', 'max:191'],
                'KataSandi' => ['required', 'string', 'max:255', Password::min(12)->letters()->numbers()],
                'KonfirmasiKataSandi' => ['required', 'same:KataSandi'],
            ],
        );

        if ($validasi->fails()) {
            foreach ($validasi->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        try {
            $pengguna = $buatSuperAdmin->Jalankan($nama, $email, $kataSandi);
        } catch (PelanggaranAturanBisnis $galat) {
            $this->error($galat->getMessage());

            return self::FAILURE;
        }

        $this->info("Super Admin {$pengguna->Email} dibuat. Aktifkan verifikasi dua langkah saat pertama masuk.");
        $this->warn('Undang minimal satu Super Admin lagi (BR-P01.1: minimal 2 Super Admin aktif).');

        return self::SUCCESS;
    }
}
