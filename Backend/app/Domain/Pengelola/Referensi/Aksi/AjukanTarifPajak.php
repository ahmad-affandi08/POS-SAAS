<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-02 langkah 2→3: draf diajukan untuk ditinjau. Dasar hukum wajib dilampirkan. Pengaju = pelaku.
 */
final class AjukanTarifPajak
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, TarifPajak $tarif): void
    {
        DB::transaction(function () use ($pelaku, $tarif): void {
            $tarif = TarifPajak::query()->lockForUpdate()->findOrFail($tarif->Id);

            if (! $tarif->Status->BisaBerubahKe(StatusDataMaster::MenungguTinjauan)) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Hanya draf yang bisa diajukan.');
            }

            if (blank($tarif->NomorDasarHukum)) {
                throw new PelanggaranAturanBisnis('DasarHukumWajib', 'Lampirkan nomor dasar hukum (PMK/Perda) sebelum mengajukan.', 'NomorDasarHukum');
            }

            TinjauTarifPajak::PastikanBelumLewat($tarif);

            $tarif->update([
                'DaftarIdPenyusun' => TinjauanDataMaster::TambahPenyusun($tarif->DaftarIdPenyusun, $pelaku->Id),
                'Status' => StatusDataMaster::MenungguTinjauan,
                'IdPenggunaPengelolaPengaju' => $pelaku->Id,
                'DiajukanPada' => now(),
                // Putaran baru: persetujuan dari pengajuan sebelumnya (yang ditolak) tidak ikut dihitung.
                'PutaranTinjauan' => $tarif->PutaranTinjauan + 1,
            ]);

            $this->audit->Catat(
                'referensi.tarif-pajak.ajukan',
                $tarif,
                nilaiLama: ['Status' => StatusDataMaster::Draf->value],
                nilaiBaru: ['Status' => $tarif->Status->value, 'Putaran' => $tarif->PutaranTinjauan],
                idPelaku: $pelaku->Id,
            );
        });
    }
}
