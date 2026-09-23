<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Model\HariLibur;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan semua draf hari libur satu tahun untuk ditinjau (P-02). Dasar hukum (misal SKB 3 Menteri) wajib.
 */
final class AjukanHariLiburTahun
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, int $tahun): int
    {
        return DB::transaction(function () use ($pelaku, $tahun): int {
            $draf = HariLibur::query()
                ->whereYear('Tanggal', $tahun)
                ->where('Status', StatusDataMaster::Draf->value)
                ->lockForUpdate()
                ->get();

            if ($draf->isEmpty()) {
                throw new PelanggaranAturanBisnis('TidakAdaDraf', "Tidak ada draf hari libur tahun {$tahun} untuk diajukan.");
            }

            if ($draf->contains(fn (HariLibur $hari) => blank($hari->NomorDasarHukum))) {
                throw new PelanggaranAturanBisnis('DasarHukumWajib', 'Lengkapi nomor dasar hukum (misal SKB 3 Menteri) di setiap draf sebelum mengajukan.');
            }

            $waktu = now();

            foreach ($draf as $hari) {
                $hari->update(['Status' => StatusDataMaster::MenungguTinjauan, 'IdPenggunaPengelolaPengaju' => $pelaku->Id, 'DiajukanPada' => $waktu]);
            }

            $this->audit->Catat('referensi.hari-libur.ajukan', nilaiBaru: ['Tahun' => $tahun, 'Jumlah' => $draf->count()], idPelaku: $pelaku->Id);

            return $draf->count();
        });
    }
}
