<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Kueri;

use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Tenant\Model\RilisAplikasi;
use Illuminate\Support\Facades\DB;

/**
 * BR-P10.2: sebelum menaikkan versi minimum, jumlah perangkat aktif di platform itu yang masih di bawah versi tersebut,
 * dan berapa di antaranya yang masih punya outbox tertunda (dilaporkan aplikasi lewat header `X-Outbox-Tertunda`).
 * Perangkat itu tetap boleh mengirim outbox (§14.6). Dibaca lintas tenant lewat `KonteksPengelola` (diaudit).
 */
final class DampakVersiMinimum
{
    public function __construct(private readonly KonteksPengelola $konteks) {}

    /**
     * @return array{PerangkatDiBawah: int, PerangkatDiBawahDenganOutbox: int, OutboxTertunda: int}
     */
    public function Hitung(string $platform, string $versi): array
    {
        return $this->konteks->JalankanLintasTenant("BR-P10.2: dampak versi minimum {$platform} {$versi}", function (KonteksPengelola $k) use ($platform, $versi): array {
            $hasil = ['PerangkatDiBawah' => 0, 'PerangkatDiBawahDenganOutbox' => 0, 'OutboxTertunda' => 0];

            foreach ($k->KueriLintas(Perangkat::class)
                ->where('Platform', $platform)
                ->whereNull('DicabutPada')
                ->whereNotNull('DiaktifkanPada')
                ->whereNotNull('VersiAplikasi')
                ->groupBy('VersiAplikasi')
                ->toBase()
                ->get(['VersiAplikasi', DB::raw('COUNT(*) AS Jumlah'), DB::raw('SUM(CASE WHEN `JumlahOutboxTertunda` > 0 THEN 1 ELSE 0 END) AS DenganOutbox'), DB::raw('SUM(`JumlahOutboxTertunda`) AS Outbox')]) as $b) {
                if (RilisAplikasi::BandingkanVersi((string) $b->VersiAplikasi, $versi) < 0) {
                    $hasil['PerangkatDiBawah'] += (int) $b->Jumlah;
                    $hasil['PerangkatDiBawahDenganOutbox'] += (int) $b->DenganOutbox;
                    $hasil['OutboxTertunda'] += (int) $b->Outbox;
                }
            }

            return $hasil;
        });
    }
}
