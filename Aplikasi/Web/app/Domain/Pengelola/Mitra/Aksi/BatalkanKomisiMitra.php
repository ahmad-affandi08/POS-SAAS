<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Mitra\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusKomisiMitra;
use App\Domain\Tenant\Model\KomisiMitra;
use Illuminate\Support\Facades\DB;

/**
 * Clawback BR-P12.1: komisi tertunda dibatalkan (tagihannya dikembalikan/refund, atau atribusi keliru). Komisi yang
 * sudah dicairkan tidak bisa dibatalkan di sini; potong dari pencairan berikutnya lewat catatan Keuangan.
 */
final class BatalkanKomisiMitra
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, KomisiMitra $komisi, string $alasan): void
    {
        DB::transaction(function () use ($pelaku, $komisi, $alasan): void {
            $komisi = KomisiMitra::query()->whereKey($komisi->Id)->lockForUpdate()->firstOrFail();

            if ($komisi->Status !== StatusKomisiMitra::Tertunda) {
                throw new PelanggaranAturanBisnis('KomisiTidakTertunda', 'Hanya komisi tertunda yang bisa dibatalkan.', 'Status');
            }

            $komisi->UbahStatus(StatusKomisiMitra::Dibatalkan);
            $komisi->AlasanBatal = trim($alasan);
            $komisi->save();
            $this->audit->Catat('mitra.komisi.batal', $komisi, ['Status' => 'Tertunda'], ['Status' => 'Dibatalkan', 'Jumlah' => $komisi->Jumlah], alasan: trim($alasan), idPelaku: $pelaku->Id);
        });
    }
}
