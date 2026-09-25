<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusStokOpname;
use App\Domain\Persediaan\Model\StokOpname;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan stok opname yang masih aktif (F-05b): tanpa mutasi atau jurnal; lokasi bisa diopname lagi.
 * Opname yang sudah disetujui tidak bisa dibatalkan (koreksi = opname/penyesuaian baru). Audit `stok-opname.batal`.
 */
final class BatalkanStokOpname
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(StokOpname $opname, string $alasan, int $idPengguna): StokOpname
    {
        return DB::transaction(function () use ($opname, $alasan, $idPengguna): StokOpname {
            $opname = StokOpname::query()->whereKey($opname->Id)->lockForUpdate()->firstOrFail();

            if ($opname->Status === StatusStokOpname::Dibatalkan) {
                return $opname;
            }

            if (! $opname->Status->CekAktif()) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Stok opname yang sudah disetujui tidak bisa dibatalkan. Koreksi lewat penyesuaian stok.');
            }

            $asal = $opname->Status;
            $opname->UbahStatus(StatusStokOpname::Dibatalkan);
            $opname->fill(['AlasanBatal' => mb_substr(trim($alasan), 0, 255), 'DibatalkanOleh' => $idPengguna, 'DibatalkanPada' => now(), 'DiubahOleh' => $idPengguna]);
            $opname->save();
            $this->riwayat->Catat(StokOpname::JENIS_DOKUMEN, $opname->Id, $asal->value, StatusStokOpname::Dibatalkan->value, $idPengguna, $opname->AlasanBatal);
            $this->audit->Catat('stok-opname.batal', $opname, ['Status' => $asal->value], ['Status' => StatusStokOpname::Dibatalkan->value, 'AlasanBatal' => $opname->AlasanBatal], idPengguna: $idPengguna);

            return $opname;
        });
    }
}
