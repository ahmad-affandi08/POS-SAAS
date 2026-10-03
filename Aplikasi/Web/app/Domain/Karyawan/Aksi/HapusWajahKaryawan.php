<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\WajahKaryawan;
use Illuminate\Support\Facades\DB;

/**
 * F-18 bagian 4 (D-37): menghapus wajah terdaftar karyawan beserta fotonya (atur ulang oleh pengelola, atau otomatis
 * saat karyawan dinonaktifkan: data biometrik tidak disimpan lebih lama dari keperluannya, UU PDP Pasal 43).
 */
final class HapusWajahKaryawan
{
    public function __construct(
        private readonly PenyimpanSwafoto $penyimpan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Karyawan $karyawan, int $idPengguna): int
    {
        $jumlah = $this->HapusSemua($karyawan);

        if ($jumlah > 0) {
            $this->audit->Catat('karyawan.wajah.hapus', $karyawan, null, ['Nama' => $karyawan->Nama], idPengguna: $idPengguna);
        }

        return $jumlah;
    }

    /** Tanpa log audit: dipakai di dalam aksi lain yang sudah mencatat jejaknya sendiri. */
    public function HapusSemua(Karyawan $karyawan): int
    {
        $daftar = WajahKaryawan::query()->where('IdKaryawan', $karyawan->Id)->get();

        foreach ($daftar as $wajah) {
            $path = $wajah->PathFoto;
            $wajah->delete();
            // Berkas dihapus setelah commit: transaksi pemanggil yang batal tidak meninggalkan baris tanpa foto.
            DB::afterCommit(fn () => array_map($this->penyimpan->Hapus(...), $path));
        }

        return $daftar->count();
    }
}
