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
 * Menyelesaikan hitung stok opname dan mengajukannya untuk ditinjau (F-05b): Berlangsung → Ditinjau. Minimal satu
 * baris sudah dihitung (`OpnameBelumDihitung`); baris yang belum dihitung tidak disesuaikan. Audit `stok-opname.ajukan`.
 */
final class AjukanTinjauanStokOpname
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(StokOpname $opname, int $idPengguna): StokOpname
    {
        return DB::transaction(function () use ($opname, $idPengguna): StokOpname {
            $opname = StokOpname::query()->whereKey($opname->Id)->lockForUpdate()->firstOrFail();

            if ($opname->Status === StatusStokOpname::Ditinjau) {
                return $opname;
            }

            if ($opname->Status !== StatusStokOpname::Berlangsung) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Stok opname berstatus {$opname->Status->AmbilLabel()} tidak bisa diajukan.");
            }

            if ($opname->JumlahDihitung === 0) {
                throw new PelanggaranAturanBisnis('OpnameBelumDihitung', 'Belum ada barang yang dihitung. Isi jumlah fisik minimal satu barang lalu simpan.');
            }

            $opname->UbahStatus(StatusStokOpname::Ditinjau);
            $opname->fill(['DiajukanOleh' => $idPengguna, 'DiajukanPada' => now(), 'DiubahOleh' => $idPengguna]);
            $opname->save();
            $this->riwayat->Catat(StokOpname::JENIS_DOKUMEN, $opname->Id, StatusStokOpname::Berlangsung->value, StatusStokOpname::Ditinjau->value, $idPengguna);
            $this->audit->Catat('stok-opname.ajukan', $opname, ['Status' => StatusStokOpname::Berlangsung->value], ['Status' => StatusStokOpname::Ditinjau->value, 'JumlahDihitung' => $opname->JumlahDihitung], idPengguna: $idPengguna);

            return $opname;
        });
    }
}
