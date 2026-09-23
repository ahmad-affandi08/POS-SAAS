<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Aksi;

use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Layanan\PenulisPesanTiket;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-09: tiket `Selesai` yang tidak dibuka lagi dalam 7 hari ditutup otomatis (terjadwal harian, routes/console.php).
 */
final class TutupTiketSelesaiOtomatis
{
    public function __construct(
        private readonly KonteksPengelola $konteks,
        private readonly PenulisPesanTiket $penulisPesan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(): int
    {
        $hari = (int) config('dukungan.HariBukaUlang');

        return $this->konteks->JalankanLintasTenant("Menutup otomatis tiket selesai lebih dari {$hari} hari", function () use ($hari): int {
            $jumlah = 0;
            $daftar = $this->konteks->KueriLintas(TiketDukungan::class)
                ->where('Status', StatusTiketDukungan::Selesai->value)
                ->where('DiselesaikanPada', '<', now()->subDays($hari))
                ->orderBy('Id')
                ->get(['Id']);

            foreach ($daftar as $baris) {
                DB::transaction(function () use ($baris, $hari, &$jumlah): void {
                    $tiket = $this->konteks->KueriLintas(TiketDukungan::class)->lockForUpdate()->whereKey($baris->Id)->first();

                    if ($tiket === null || $tiket->Status !== StatusTiketDukungan::Selesai || $tiket->CekBisaDibukaLagi()) {
                        return;
                    }

                    $tiket->Status = StatusTiketDukungan::Ditutup;
                    $tiket->DitutupPada = now();
                    $tiket->save();
                    $this->penulisPesan->TulisSistem($tiket, "Tiket ditutup otomatis {$hari} hari setelah selesai.");
                    $this->audit->Catat(
                        'dukungan.tiket.tutup-otomatis',
                        $tiket,
                        nilaiLama: ['Status' => StatusTiketDukungan::Selesai->value],
                        nilaiBaru: ['Status' => StatusTiketDukungan::Ditutup->value],
                        idTenant: $tiket->IdTenant,
                    );
                    $jumlah++;
                });
            }

            return $jumlah;
        });
    }
}
