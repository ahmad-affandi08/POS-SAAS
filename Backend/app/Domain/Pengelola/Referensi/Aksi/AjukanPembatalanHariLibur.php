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
 * BR-P02.6: mengajukan pembatalan hari libur terbit (misal cuti bersama dibatalkan pemerintah). Hari libur tetap
 * berlaku sampai pembatalan disetujui.
 */
final class AjukanPembatalanHariLibur
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, HariLibur $hariLibur, string $alasan): void
    {
        DB::transaction(function () use ($pelaku, $hariLibur, $alasan): void {
            $hariLibur = HariLibur::query()->lockForUpdate()->findOrFail($hariLibur->Id);

            if ($hariLibur->Status !== StatusDataMaster::Terbit) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Hanya hari libur terbit yang bisa diajukan pembatalannya. Draf cukup dihapus.');
            }

            if ($hariLibur->CekPembatalanMenunggu()) {
                throw new PelanggaranAturanBisnis('PengajuanBerjalan', 'Pembatalan hari libur ini sudah diajukan dan menunggu tinjauan.');
            }

            $hariLibur->update([
                'PembatalanDiajukanPada' => now(),
                'IdPenggunaPengelolaPengajuBatal' => $pelaku->Id,
                'AlasanPembatalan' => $alasan,
                'PutaranTinjauan' => $hariLibur->PutaranTinjauan + 1,
            ]);

            $this->audit->Catat(
                'referensi.hari-libur.ajukan-batal',
                $hariLibur,
                nilaiLama: ['Status' => StatusDataMaster::Terbit->value],
                nilaiBaru: ['PembatalanDiajukan' => true],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
            );
        });
    }
}
