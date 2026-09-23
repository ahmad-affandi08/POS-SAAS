<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\OverrideTenant;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Perpanjang trial (P-07, BR-P07.6): hanya saat langganan masih `Trial`, maksimal 2 kali per tenant dan 14 hari per
 * perpanjangan, alasan wajib. Trial baru berakhir = (akhir trial sekarang, atau sekarang bila sudah lewat tetapi belum
 * diproses perintah akhir trial) + jumlah hari. Setiap perpanjangan dicatat sebagai `OverrideTenant` jenis Trial
 * (dasar hitungan batas) dan di `LogAuditPengelola` (BR-P07.3).
 */
final class PerpanjangTrial
{
    public const MAKS_KALI = 2;

    public const MAKS_HARI = 14;

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant, int $hari, string $alasan): Langganan
    {
        if ($hari < 1 || $hari > self::MAKS_HARI) {
            throw new PelanggaranAturanBisnis('BR-P07.6', 'Perpanjangan trial 1 sampai '.self::MAKS_HARI.' hari.', 'Hari');
        }

        return DB::transaction(function () use ($pelaku, $tenant, $hari, $alasan): Langganan {
            // Kunci baris langganan: perintah akhir trial dan pembayaran (P-08) juga mengunci baris ini.
            $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Tenant ini belum punya langganan.');

            if ($langganan->Status !== StatusLangganan::Trial || $langganan->TrialBerakhirPada === null) {
                throw new PelanggaranAturanBisnis(
                    'BR-P07.6',
                    "Trial hanya bisa diperpanjang saat langganan berstatus Trial (sekarang {$langganan->Status->AmbilLabel()}).",
                );
            }

            $sudah = OverrideTenant::query()
                ->where('IdTenant', $tenant->Id)
                ->where('Jenis', JenisOverride::Trial->value)
                ->count();

            if ($sudah >= self::MAKS_KALI) {
                throw new PelanggaranAturanBisnis('BR-P07.6', 'Trial tenant ini sudah diperpanjang '.self::MAKS_KALI.' kali. Batas tercapai.');
            }

            $lama = $langganan->TrialBerakhirPada->copy();
            $baru = ($lama->isFuture() ? $lama->copy() : now())->addDays($hari);

            $langganan->update(['TrialBerakhirPada' => $baru]);

            OverrideTenant::query()->create([
                'IdTenant' => $tenant->Id,
                'Jenis' => JenisOverride::Trial,
                'Kunci' => 'TrialBerakhirPada',
                'Nilai' => (string) $hari,
                'BerakhirPada' => $baru,
                'Alasan' => $alasan,
                'DibuatOleh' => $pelaku->Id,
            ]);

            $this->audit->Catat(
                'tenant.trial.perpanjang',
                $langganan,
                nilaiLama: ['TrialBerakhirPada' => $lama->toIso8601String()],
                nilaiBaru: ['TrialBerakhirPada' => $baru->toIso8601String(), 'Hari' => $hari, 'PerpanjanganKe' => $sudah + 1],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $langganan;
        });
    }
}
