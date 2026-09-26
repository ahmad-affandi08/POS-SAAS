<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\TokenAksesPengguna;

/**
 * OWN-01: mencari token akses Aplikasi Owner yang masih berlaku (belum dicabut, belum kedaluwarsa) dari token Bearer,
 * beserta penggunanya. `TerakhirDipakaiPada` diperbarui paling sering sekali per menit agar polling dasbor tidak
 * menulis ke database setiap permintaan.
 */
final class TokenPenggunaBerdasarkanToken
{
    public function Cari(string $token, string $kemampuan = TokenAksesPengguna::KEMAMPUAN_PEMILIK): ?TokenAksesPengguna
    {
        if (preg_match('/^[A-Za-z0-9_-]{40,200}$/', $token) !== 1) {
            return null;
        }

        $hash = TokenAksesPengguna::BuatHashToken($token);
        $baris = TokenAksesPengguna::query()->with('Pengguna')->where('HashToken', $hash)->first();

        if ($baris === null || ! hash_equals($baris->HashToken, $hash) || $baris->Kemampuan !== $kemampuan || ! $baris->CekBerlaku()) {
            return null;
        }

        if ($baris->TerakhirDipakaiPada === null || $baris->TerakhirDipakaiPada->lt(now()->subMinute())) {
            TokenAksesPengguna::query()->whereKey($baris->Id)->update(['TerakhirDipakaiPada' => now()]);
        }

        return $baris;
    }
}
