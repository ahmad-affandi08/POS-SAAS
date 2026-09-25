<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\Karyawan;

/**
 * Nonaktifkan/aktifkan karyawan (F-18, izin `karyawan.kelola`). Nonaktif = tidak bisa absen di POS dan tidak muncul
 * di jadwal baru; absensi & jadwal lama tetap. Idempoten. Audit `karyawan.nonaktifkan|aktifkan`.
 */
final class UbahStatusKaryawan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Karyawan $karyawan, StatusKaryawan $status, int $idPengguna): Karyawan
    {
        if ($karyawan->Status === $status) {
            return $karyawan;
        }

        $lama = $karyawan->Status;
        $karyawan->Status = $status;
        $karyawan->save();
        $this->audit->Catat(
            $status === StatusKaryawan::Aktif ? 'karyawan.aktifkan' : 'karyawan.nonaktifkan',
            $karyawan,
            ['Status' => $lama->value],
            ['Status' => $status->value],
            idPengguna: $idPengguna,
        );

        return $karyawan;
    }
}
