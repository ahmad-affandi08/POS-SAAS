<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\HargaPaket;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan draf harga paket untuk ditinjau Super Admin (BR-P04.5). Tanggal berlaku tidak boleh di masa lalu.
 */
final class AjukanHargaPaket
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, HargaPaket $harga): void
    {
        DB::transaction(function () use ($pelaku, $harga): void {
            $harga = HargaPaket::query()->lockForUpdate()->findOrFail($harga->Id);

            if (! $harga->Status->BisaBerubahKe(StatusDataMaster::MenungguTinjauan)) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Hanya draf harga yang bisa diajukan.');
            }

            TinjauHargaPaket::PastikanBelumLewat($harga);

            $harga->update([
                'Status' => StatusDataMaster::MenungguTinjauan,
                'IdPenggunaPengelolaPengaju' => $pelaku->Id,
                'DiajukanPada' => now(),
                'PutaranTinjauan' => $harga->PutaranTinjauan + 1,
                'DaftarIdPenyusun' => TinjauanDataMaster::TambahPenyusun($harga->DaftarIdPenyusun, $pelaku->Id),
            ]);

            $this->audit->Catat(
                'katalog.harga.ajukan',
                $harga,
                nilaiLama: ['Status' => StatusDataMaster::Draf->value],
                nilaiBaru: ['Status' => StatusDataMaster::MenungguTinjauan->value, 'Putaran' => $harga->PutaranTinjauan],
                idPelaku: $pelaku->Id,
            );
        });
    }
}
