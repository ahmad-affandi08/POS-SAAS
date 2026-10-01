<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\StatusKampanye;
use App\Domain\Pelanggan\Enum\StatusPenerimaKampanye;
use App\Domain\Pelanggan\Layanan\PengirimKampanyePesan;
use App\Domain\Pelanggan\Model\KampanyePesan;
use App\Domain\Pelanggan\Model\PenerimaKampanye;
use Illuminate\Support\Facades\DB;

/**
 * CRM-07: batalkan kampanye draf, terjadwal, atau yang sedang dikirim. Penerima yang belum dapat giliran menjadi
 * `Dilewati`; yang sudah terkirim tetap tercatat. Audit `kampanye-pesan.batalkan`.
 */
final class BatalkanKampanyePesan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(KampanyePesan $kampanye, int $idPengguna): KampanyePesan
    {
        return DB::transaction(function () use ($kampanye, $idPengguna): KampanyePesan {
            $terkunci = KampanyePesan::query()->whereKey($kampanye->Id)->lockForUpdate()->firstOrFail();
            $asal = $terkunci->Status;

            if (! $asal->BisaBerubahKe(StatusKampanye::Dibatalkan)) {
                throw new PelanggaranAturanBisnis('TidakBisaDibatalkan', 'Kampanye ini sudah selesai atau dibatalkan.', 'Umum', 409);
            }

            PenerimaKampanye::query()
                ->where('IdKampanyePesan', $terkunci->Id)
                ->where('Status', StatusPenerimaKampanye::Diantrekan->value)
                ->update(['Status' => StatusPenerimaKampanye::Dilewati->value, 'PesanGalat' => 'Kampanye dibatalkan.']);

            $terkunci->UbahStatus(StatusKampanye::Dibatalkan);
            $terkunci->fill(['DibatalkanOleh' => $idPengguna, 'SelesaiPada' => now()]);
            PengirimKampanyePesan::HitungUlang($terkunci);
            $terkunci->save();

            $this->audit->Catat('kampanye-pesan.batalkan', $terkunci, ['Status' => $asal->value], [
                'Status' => $terkunci->Status->value,
                'JumlahTerkirim' => $terkunci->JumlahTerkirim,
            ], idPengguna: $idPengguna);

            return $terkunci;
        });
    }
}
