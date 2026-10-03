<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Model\Karyawan;

/**
 * Nonaktifkan/aktifkan karyawan (F-18, izin `karyawan.kelola`). Nonaktif = tidak bisa absen di POS dan tidak muncul
 * di jadwal baru; absensi & jadwal lama tetap. Idempoten. Audit `karyawan.nonaktifkan|aktifkan`.
 *
 * F-18 bagian 4 (D-37): nonaktif juga mencabut tautan absen dan menghapus wajah terdaftar (data biometrik tidak
 * disimpan lebih lama dari keperluannya).
 */
final class UbahStatusKaryawan
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly HapusWajahKaryawan $hapusWajah,
    ) {}

    public function Jalankan(Karyawan $karyawan, StatusKaryawan $status, int $idPengguna): Karyawan
    {
        if ($karyawan->Status === $status) {
            return $karyawan;
        }

        $lama = $karyawan->Status;
        $karyawan->Status = $status;

        if ($status === StatusKaryawan::Nonaktif) {
            $karyawan->forceFill(['TokenAbsen' => null, 'HashTokenAbsen' => null, 'TokenAbsenDibuatPada' => null]);
            $this->hapusWajah->HapusSemua($karyawan);
        }

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
