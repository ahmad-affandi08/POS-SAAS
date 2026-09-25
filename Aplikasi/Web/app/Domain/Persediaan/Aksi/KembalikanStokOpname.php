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
 * Mengembalikan stok opname yang sedang ditinjau ke hitung ulang (F-05b): Ditinjau → Berlangsung, dengan alasan
 * (misal selisih besar perlu dihitung ulang). Audit `stok-opname.kembalikan`.
 */
final class KembalikanStokOpname
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(StokOpname $opname, string $alasan, int $idPengguna): StokOpname
    {
        return DB::transaction(function () use ($opname, $alasan, $idPengguna): StokOpname {
            $opname = StokOpname::query()->whereKey($opname->Id)->lockForUpdate()->firstOrFail();

            if ($opname->Status !== StatusStokOpname::Ditinjau) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Stok opname berstatus {$opname->Status->AmbilLabel()} tidak bisa dikembalikan ke hitung ulang.");
            }

            $alasan = mb_substr(trim($alasan), 0, 255);
            $opname->UbahStatus(StatusStokOpname::Berlangsung);
            $opname->DiubahOleh = $idPengguna;
            $opname->save();
            $this->riwayat->Catat(StokOpname::JENIS_DOKUMEN, $opname->Id, StatusStokOpname::Ditinjau->value, StatusStokOpname::Berlangsung->value, $idPengguna, $alasan);
            $this->audit->Catat('stok-opname.kembalikan', $opname, ['Status' => StatusStokOpname::Ditinjau->value], ['Status' => StatusStokOpname::Berlangsung->value, 'Alasan' => $alasan], idPengguna: $idPengguna);

            return $opname;
        });
    }
}
