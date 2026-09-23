<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Kueri\UndanganBerlaku;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-01 langkah 4 (bagian 1): anggota membuat akun dari undangan. 2FA diaktifkan di langkah berikutnya
 * dan wajib sebelum menu apa pun bisa dibuka (perantara WajibDuaFaktor).
 */
final class TerimaUndangan
{
    public function __construct(
        private readonly UndanganBerlaku $undanganBerlaku,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(string $token, string $nama, string $kataSandi): PenggunaPengelola
    {
        return DB::transaction(function () use ($token, $nama, $kataSandi): PenggunaPengelola {
            $undangan = $this->undanganBerlaku->Cari($token, kunci: true);

            if ($undangan === null) {
                throw new PelanggaranAturanBisnis('UndanganTidakBerlaku', 'Undangan tidak berlaku. Minta Super Admin mengirim undangan baru.');
            }

            if (PenggunaPengelola::query()->where('Email', $undangan->Email)->exists()) {
                throw new PelanggaranAturanBisnis('EmailSudahTerdaftar', 'Email ini sudah terdaftar sebagai anggota tim internal.');
            }

            $pengguna = PenggunaPengelola::query()->create([
                'Nama' => $nama,
                'Email' => $undangan->Email,
                'KataSandi' => $kataSandi,
                'Aktif' => true,
            ]);

            $idPeran = PeranPengelola::query()->whereIn('Kode', $undangan->KodePeran)->pluck('Id')->all();
            $pengguna->Peran()->attach($idPeran);

            $undangan->update(['DiterimaPada' => now()]);

            $this->audit->Catat(
                'tim.anggota.terima-undangan',
                $pengguna,
                nilaiBaru: ['Nama' => $nama, 'Email' => $undangan->Email, 'KodePeran' => $undangan->KodePeran],
                idPelaku: $pengguna->Id,
            );

            return $pengguna;
        });
    }
}
