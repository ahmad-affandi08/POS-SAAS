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
 * BR-P02.6: meninjau pembatalan hari libur (1 penyetuju selain pengaju pembatalan). Setuju → Dibatalkan (baris tetap
 * tersimpan); Tolak → hari libur tetap terbit.
 */
final class TinjauPembatalanHariLibur
{
    public const JENIS_DATA = 'PembatalanHariLibur';

    public function __construct(
        private readonly TinjauanDataMaster $tinjauan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $peninjau, HariLibur $hariLibur, KeputusanTinjauan $keputusan, ?string $catatan = null): StatusDataMaster
    {
        return DB::transaction(function () use ($peninjau, $hariLibur, $keputusan, $catatan): StatusDataMaster {
            $hariLibur = HariLibur::query()->lockForUpdate()->findOrFail($hariLibur->Id);

            if (! $hariLibur->CekPembatalanMenunggu()) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Tidak ada pengajuan pembatalan untuk hari libur ini.');
            }

            $this->tinjauan->CatatKeputusan(
                self::JENIS_DATA,
                $hariLibur->Id,
                $hariLibur->PutaranTinjauan,
                TinjauanDataMaster::GabungTerlarang([[[], $hariLibur->IdPenggunaPengelolaPengajuBatal]]),
                $peninjau,
                $keputusan,
                $catatan,
            );

            if ($keputusan === KeputusanTinjauan::Setuju) {
                $hariLibur->update(['Status' => StatusDataMaster::Dibatalkan, 'DibatalkanPada' => now()]);
            } else {
                $hariLibur->update(['PembatalanDiajukanPada' => null, 'IdPenggunaPengelolaPengajuBatal' => null, 'AlasanPembatalan' => null]);
            }

            $this->audit->Catat(
                $keputusan === KeputusanTinjauan::Setuju ? 'referensi.hari-libur.batal' : 'referensi.hari-libur.tolak-batal',
                $hariLibur,
                nilaiLama: ['Status' => StatusDataMaster::Terbit->value],
                nilaiBaru: ['Status' => $hariLibur->Status->value],
                alasan: $catatan,
                idPelaku: $peninjau->Id,
            );

            return $hariLibur->Status;
        });
    }
}
