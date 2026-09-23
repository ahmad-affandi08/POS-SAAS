<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Model\HariLibur;
use Illuminate\Support\Facades\DB;

/**
 * Meninjau pengajuan hari libur satu tahun (P-02, BR-P02.2: 1 penyetuju selain pengaju). Tolak → kembali ke Draf.
 */
final class TinjauHariLiburTahun
{
    public const JENIS_DATA = 'HariLiburTahun';

    public const PENYETUJU = 1;

    public function __construct(
        private readonly TinjauanDataMaster $tinjauan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $peninjau, int $tahun, KeputusanTinjauan $keputusan, ?string $catatan = null): StatusDataMaster
    {
        return DB::transaction(function () use ($peninjau, $tahun, $keputusan, $catatan): StatusDataMaster {
            $diajukan = HariLibur::query()
                ->whereYear('Tanggal', $tahun)
                ->where('Status', StatusDataMaster::MenungguTinjauan->value)
                ->lockForUpdate()
                ->get();
            $contoh = $diajukan->first();

            if ($contoh === null || $contoh->DiajukanPada === null) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Tidak ada hari libur tahun {$tahun} yang menunggu tinjauan.");
            }

            $jumlahSetuju = $this->tinjauan->CatatKeputusan(
                self::JENIS_DATA,
                $tahun,
                $contoh->IdPenggunaPengelolaPengaju,
                $contoh->DiajukanPada,
                $peninjau,
                $keputusan,
                $catatan,
            );

            $statusBaru = match (true) {
                $keputusan === KeputusanTinjauan::Tolak => StatusDataMaster::Draf,
                $jumlahSetuju >= self::PENYETUJU => StatusDataMaster::Terbit,
                default => StatusDataMaster::MenungguTinjauan,
            };

            if ($statusBaru !== StatusDataMaster::MenungguTinjauan) {
                foreach ($diajukan as $hari) {
                    $hari->update(['Status' => $statusBaru]);
                }
            }

            $this->audit->Catat(
                $statusBaru === StatusDataMaster::Terbit ? 'referensi.hari-libur.terbit' : 'referensi.hari-libur.tolak',
                nilaiBaru: ['Tahun' => $tahun, 'Jumlah' => $diajukan->count(), 'Status' => $statusBaru->value],
                alasan: $catatan,
                idPelaku: $peninjau->Id,
            );

            return $statusBaru;
        });
    }
}
