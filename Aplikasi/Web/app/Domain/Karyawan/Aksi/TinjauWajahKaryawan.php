<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Enum\StatusWajahKaryawan;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\WajahKaryawan;
use Illuminate\Support\Facades\DB;

/**
 * F-18 bagian 4 (D-37): pengelola (`karyawan.kelola`) menyetujui atau menolak wajah yang didaftarkan karyawan setelah
 * melihat fotonya. Ditolak wajib beralasan; sidik & foto pendaftaran yang ditolak langsung dihapus sehingga yang
 * tersisa hanya status & alasannya, dan karyawan bisa mendaftar ulang.
 */
final class TinjauWajahKaryawan
{
    public function __construct(
        private readonly PenyimpanSwafoto $penyimpan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(WajahKaryawan $wajah, bool $setujui, ?string $alasan, int $idPengguna): WajahKaryawan
    {
        $tujuan = $setujui ? StatusWajahKaryawan::Disetujui : StatusWajahKaryawan::Ditolak;
        $alasan = $alasan === null ? null : trim($alasan);

        if (! $setujui && ($alasan === null || $alasan === '')) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan penolakan agar karyawan tahu yang harus diperbaiki.', 'Alasan');
        }

        $pathLama = [];
        $wajah = DB::transaction(function () use ($wajah, $tujuan, $alasan, $idPengguna, &$pathLama): WajahKaryawan {
            $terkini = WajahKaryawan::query()->lockForUpdate()->findOrFail($wajah->Id);

            if (! $terkini->Status->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Wajah ini sudah ditinjau.', 'Wajah');
            }

            if ($tujuan === StatusWajahKaryawan::Ditolak) {
                $pathLama = $terkini->PathFoto;
                $terkini->SidikWajah = [];
                $terkini->PathFoto = [];
                $terkini->AlasanTolak = $alasan;
            }

            $terkini->Status = $tujuan;
            $terkini->DitinjauOleh = $idPengguna;
            $terkini->DitinjauPada = now();
            $terkini->save();

            return $terkini;
        });

        array_map($this->penyimpan->Hapus(...), $pathLama);
        $this->audit->Catat($setujui ? 'karyawan.wajah.setujui' : 'karyawan.wajah.tolak', $wajah, null, array_filter(['Alasan' => $alasan]), idPengguna: $idPengguna);

        return $wajah;
    }
}
