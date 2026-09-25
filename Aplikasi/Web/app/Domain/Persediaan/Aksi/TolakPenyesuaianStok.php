<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use Illuminate\Support\Facades\DB;

/**
 * Menolak penyesuaian stok yang menunggu persetujuan (F-05b): kembali ke Draf dengan alasan (`AlasanTolak`) supaya
 * pembuat bisa memperbaiki lalu mengajukan ulang, atau membatalkannya. Tanpa mutasi atau jurnal. Audit
 * `penyesuaian-stok.tolak`.
 */
final class TolakPenyesuaianStok
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PenyesuaianStok $dokumen, string $alasan, int $idPengguna): PenyesuaianStok
    {
        return DB::transaction(function () use ($dokumen, $alasan, $idPengguna): PenyesuaianStok {
            $dokumen = PenyesuaianStok::query()->whereKey($dokumen->Id)->lockForUpdate()->firstOrFail();

            if ($dokumen->Status !== StatusPenyesuaianStok::MenungguPersetujuan) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Penyesuaian berstatus {$dokumen->Status->AmbilLabel()} tidak sedang menunggu persetujuan.");
            }

            $alasan = mb_substr(trim($alasan), 0, 255);
            $dokumen->UbahStatus(StatusPenyesuaianStok::Draf);
            $dokumen->fill(['AlasanTolak' => $alasan, 'PerluPersetujuan' => false, 'DiajukanOleh' => null, 'DiajukanPada' => null, 'DiubahOleh' => $idPengguna]);
            $dokumen->save();
            $this->riwayat->Catat(PenyesuaianStok::JENIS_DOKUMEN, $dokumen->Id, StatusPenyesuaianStok::MenungguPersetujuan->value, StatusPenyesuaianStok::Draf->value, $idPengguna, $alasan);
            $this->audit->Catat('penyesuaian-stok.tolak', $dokumen, ['Status' => StatusPenyesuaianStok::MenungguPersetujuan->value], ['Status' => StatusPenyesuaianStok::Draf->value, 'AlasanTolak' => $alasan], idPengguna: $idPengguna);

            return $dokumen;
        });
    }
}
