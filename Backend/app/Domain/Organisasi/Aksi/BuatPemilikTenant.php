<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Membuat akun pengguna Owner beserta keanggotaannya di tenant baru (F-00 langkah 3, BR-00.1). Dipanggil di dalam
 * transaksi pendaftaran. Email & nomor WhatsApp unik per pengguna; email belum terverifikasi (BR-00.5).
 */
final class BuatPemilikTenant
{
    public function Jalankan(int $idTenant, DataPemilikBaru $data): Pengguna
    {
        if (Pengguna::query()->where('Email', $data->email)->exists()) {
            throw new PelanggaranAturanBisnis('BR-00.1', 'Email ini sudah terdaftar. Masuk dengan email tersebut.', 'Email');
        }

        if (Pengguna::query()->where('NoHp', $data->noHp)->exists()) {
            throw new PelanggaranAturanBisnis('BR-00.1', 'Nomor WhatsApp ini sudah dipakai akun lain.', 'NoHp');
        }

        $pengguna = Pengguna::query()->create([
            'Nama' => $data->nama,
            'Email' => $data->email,
            'NoHp' => $data->noHp,
            'KataSandi' => $data->kataSandi,
            'EmailDiverifikasiPada' => null,
        ]);

        TenantPengguna::query()->create(['IdTenant' => $idTenant, 'IdPengguna' => $pengguna->Id, 'Pemilik' => true]);

        return $pengguna;
    }
}
