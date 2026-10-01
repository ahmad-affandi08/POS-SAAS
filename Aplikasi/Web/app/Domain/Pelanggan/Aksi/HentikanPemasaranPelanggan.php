<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Pelanggan\Enum\StatusPenerimaKampanye;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PenerimaKampanye;
use Illuminate\Support\Facades\DB;

/**
 * CRM-07 (UU PDP: hak menarik persetujuan): pelanggan berhenti menerima pesan promosi dari tautan di pesan kampanye.
 * `SetujuPemasaran` = false dan antrean kampanye yang belum terkirim untuknya dilewati. Idempoten. Audit
 * `pelanggan.berhenti-pemasaran` tanpa data pribadi.
 */
final class HentikanPemasaranPelanggan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Pelanggan $pelanggan): void
    {
        DB::transaction(function () use ($pelanggan): void {
            $terkunci = Pelanggan::query()->whereKey($pelanggan->Id)->lockForUpdate()->firstOrFail();

            PenerimaKampanye::query()
                ->where('IdPelanggan', $terkunci->Id)
                ->where('Status', StatusPenerimaKampanye::Diantrekan->value)
                ->update(['Status' => StatusPenerimaKampanye::Dilewati->value, 'PesanGalat' => 'Pelanggan berhenti berlangganan.']);

            if (! $terkunci->SetujuPemasaran) {
                return;
            }

            $terkunci->SetujuPemasaran = false;
            $terkunci->save();
            $this->audit->Catat('pelanggan.berhenti-pemasaran', $terkunci, ['SetujuPemasaran' => true], ['SetujuPemasaran' => false, 'Sumber' => 'TautanPesan'], idTenant: $terkunci->IdTenant);
        });
    }
}
