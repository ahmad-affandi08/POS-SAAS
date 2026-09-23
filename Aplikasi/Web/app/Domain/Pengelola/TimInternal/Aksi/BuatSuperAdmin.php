<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-01 langkah 1: Super Admin dibuat lewat perintah server, bukan halaman publik.
 * 2FA tetap wajib diaktifkan saat pertama masuk (BR-P01.2).
 */
final class BuatSuperAdmin
{
    public function __construct(
        private readonly SiapkanPeranBawaan $siapkanPeranBawaan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(string $nama, string $email, string $kataSandi): PenggunaPengelola
    {
        $this->siapkanPeranBawaan->Jalankan();

        return DB::transaction(function () use ($nama, $email, $kataSandi): PenggunaPengelola {
            if (PenggunaPengelola::query()->where('Email', $email)->exists()) {
                throw new PelanggaranAturanBisnis('EmailSudahTerdaftar', 'Email ini sudah terdaftar sebagai anggota tim internal.', 'Email');
            }

            $pengguna = PenggunaPengelola::query()->create([
                'Nama' => $nama,
                'Email' => $email,
                'KataSandi' => $kataSandi,
                'Aktif' => true,
            ]);

            $superAdmin = PeranPengelola::query()->where('Kode', PeranPengelolaBawaan::SuperAdmin->value)->firstOrFail();
            $pengguna->Peran()->attach($superAdmin->Id);

            $this->audit->Catat(
                'tim.anggota.buat-super-admin',
                $pengguna,
                nilaiBaru: ['Nama' => $nama, 'Email' => $email, 'KodePeran' => [PeranPengelolaBawaan::SuperAdmin->value]],
                alasan: 'Dibuat lewat perintah server pengelola:buat-super-admin',
            );

            return $pengguna;
        });
    }
}
