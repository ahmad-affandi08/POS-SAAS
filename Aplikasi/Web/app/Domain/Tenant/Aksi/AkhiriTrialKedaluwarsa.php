<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * BR-00.3: trial yang lewat tanpa pembayaran turun ke paket Gratis, bukan dihapus. Diproses per baris dengan kunci
 * agar pembayaran yang masuk bersamaan (P-08/F-19) tidak tertimpa. Idempoten: aman dijalankan berulang.
 */
final class AkhiriTrialKedaluwarsa
{
    /**
     * @return int jumlah langganan yang diturunkan ke Gratis
     */
    public function Jalankan(): int
    {
        $paketGratis = Paket::query()->where('Kode', (string) config('tenant.KodePaketGratis'))->first()
            ?? throw new RuntimeException('Paket Gratis (config tenant.KodePaketGratis) tidak ditemukan.');
        $jumlah = 0;

        $idKedaluwarsa = Langganan::query()
            ->where('Status', StatusLangganan::Trial->value)
            ->where('TrialBerakhirPada', '<=', now())
            ->orderBy('Id')
            ->pluck('Id')
            ->map(fn (mixed $id): int => (int) $id);

        foreach ($idKedaluwarsa as $id) {
            $diturunkan = DB::transaction(function () use ($id, $paketGratis): bool {
                $langganan = Langganan::query()->lockForUpdate()->whereKey($id)->first();

                if ($langganan === null || $langganan->Status !== StatusLangganan::Trial || $langganan->TrialBerakhirPada?->isFuture()) {
                    return false;
                }

                $langganan->update(['Status' => StatusLangganan::Gratis, 'IdPaket' => $paketGratis->Id]);
                // F-02: log audit penurunan trial oleh sistem (§25 no. 17).
                app(PencatatAudit::class)->Catat('langganan.trial-berakhir', $langganan, nilaiBaru: ['Status' => StatusLangganan::Gratis->value, 'Paket' => $paketGratis->Kode], idTenant: $langganan->IdTenant);

                return true;
            });

            $jumlah += $diturunkan ? 1 : 0;
        }

        if ($jumlah > 0) {
            Log::info('Trial berakhir, langganan turun ke Gratis.', ['Jumlah' => $jumlah]);
        }

        return $jumlah;
    }
}
