<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Model\PerangkatPengguna;
use App\Domain\Organisasi\Model\TokenAksesPengguna;
use Illuminate\Database\UniqueConstraintViolationException;

/** Daftar/perbarui token FCM Aplikasi Owner. Token yang berpindah akun diambil alih sesi terbaru secara aman. */
final class DaftarkanPerangkatPengguna
{
    public function Jalankan(TokenAksesPengguna $akses, string $token, string $platform, string $nama): PerangkatPengguna
    {
        $hash = PerangkatPengguna::BuatHashToken($token);
        $isi = [
            'IdPengguna' => $akses->IdPengguna,
            'IdTokenAksesPengguna' => $akses->Id,
            'Nama' => mb_substr(trim($nama), 0, 100),
            'Platform' => $platform,
            'Token' => $token,
            'Aktif' => true,
            'TerakhirTerdaftarPada' => now(),
        ];

        try {
            return PerangkatPengguna::query()->updateOrCreate(['HashToken' => $hash], $isi);
        } catch (UniqueConstraintViolationException) {
            $perangkat = PerangkatPengguna::query()->where('HashToken', $hash)->firstOrFail();
            $perangkat->fill($isi)->save();

            return $perangkat;
        }
    }

    public function NonaktifkanMilikTokenAkses(TokenAksesPengguna $akses): void
    {
        PerangkatPengguna::query()
            ->where('IdPengguna', $akses->IdPengguna)
            ->where('IdTokenAksesPengguna', $akses->Id)
            ->update(['Aktif' => false]);
    }
}
