<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Galat\IdentitasSudahTerdaftar;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Membuat akun pengguna Owner beserta keanggotaannya di tenant baru (F-00 langkah 3, BR-00.1). Dipanggil di dalam
 * transaksi pendaftaran. Email & nomor WhatsApp unik per pengguna; email belum terverifikasi (BR-00.5).
 * Identitas yang sudah dipakai ditolak lewat `IdentitasSudahTerdaftar` tanpa membuka mana yang cocok (§25 no. 18).
 */
final class BuatPemilikTenant
{
    public function Jalankan(int $idTenant, DataPemilikBaru $data): Pengguna
    {
        $identitasPerPengguna = [];

        foreach (['Email' => 'email', 'NoHp' => 'nomor WhatsApp'] as $kolom => $label) {
            $idPemilik = Pengguna::query()->where($kolom, $kolom === 'Email' ? $data->email : $data->noHp)->value('Id');

            if (is_numeric($idPemilik)) {
                $identitasPerPengguna[(int) $idPemilik][] = $label;
            }
        }

        if ($identitasPerPengguna !== []) {
            throw new IdentitasSudahTerdaftar($identitasPerPengguna);
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
