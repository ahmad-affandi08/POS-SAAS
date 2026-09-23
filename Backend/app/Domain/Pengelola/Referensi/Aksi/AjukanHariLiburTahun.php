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
 * Selama ada pengajuan tahun yang sama yang belum ditinjau, pengajuan baru ditolak: satu tahun satu putaran,
 * sehingga semua baris dalam satu tinjauan punya pengaju yang sama (BR-P02.2).
 */
final class AjukanHariLiburTahun
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, int $tahun): int
    {
        return DB::transaction(function () use ($pelaku, $tahun): int {
            $semua = HariLibur::query()->whereYear('Tanggal', $tahun)->lockForUpdate()->get();

            if ($semua->contains(fn (HariLibur $hari) => $hari->Status === StatusDataMaster::MenungguTinjauan)) {
                throw new PelanggaranAturanBisnis(
                    'PengajuanBerjalan',
                    "Masih ada pengajuan hari libur tahun {$tahun} yang menunggu tinjauan. Tunggu hasilnya sebelum mengajukan lagi.",
                );
            }

            $draf = $semua->filter(fn (HariLibur $hari) => $hari->Status === StatusDataMaster::Draf);

            if ($draf->isEmpty()) {
                throw new PelanggaranAturanBisnis('TidakAdaDraf', "Tidak ada draf hari libur tahun {$tahun} untuk diajukan.");
            }

            if ($draf->contains(fn (HariLibur $hari) => blank($hari->NomorDasarHukum))) {
                throw new PelanggaranAturanBisnis('DasarHukumWajib', 'Lengkapi nomor dasar hukum (misal SKB 3 Menteri) di setiap draf sebelum mengajukan.');
            }

            $waktu = now();
            $putaran = (int) $semua->max('PutaranTinjauan') + 1;

            foreach ($draf as $hari) {
                $hari->update([
                    'Status' => StatusDataMaster::MenungguTinjauan,
                    'IdPenggunaPengelolaPengaju' => $pelaku->Id,
                    'DiajukanPada' => $waktu,
                    'PutaranTinjauan' => $putaran,
                ]);
            }

            $this->audit->Catat(
                'referensi.hari-libur.ajukan',
                nilaiLama: ['Status' => StatusDataMaster::Draf->value],
                nilaiBaru: ['Tahun' => $tahun, 'Jumlah' => $draf->count(), 'Putaran' => $putaran],
                idPelaku: $pelaku->Id,
            );

            return $draf->count();
        });
    }
}
