<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;

/**
 * Karyawan milik pengguna yang absen di POS (F-18). Belum ada = dibuat otomatis (nama pengguna, outlet perangkat) agar
 * absensi offline tidak hilang; audit `karyawan.tambah` bersumber `Absensi`. Dipanggil di dalam transaksi pemanggil.
 */
final class PenentuKaryawanPengguna
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Tentukan(DataAnggotaOutlet $pengguna, int $idOutlet): Karyawan
    {
        $karyawan = Karyawan::query()->where('IdPengguna', $pengguna->id)->lockForUpdate()->first();

        if ($karyawan !== null) {
            return $karyawan;
        }

        $baru = Karyawan::query()->create([
            'IdPengguna' => $pengguna->id,
            'IdOutlet' => $idOutlet,
            'Nama' => mb_substr($pengguna->nama, 0, 150),
            'DibuatOleh' => $pengguna->id,
        ]);
        $this->audit->Catat('karyawan.tambah', $baru, nilaiBaru: ['Nama' => $baru->Nama, 'Sumber' => 'Absensi'], idPengguna: $pengguna->id);

        return $baru;
    }
}
