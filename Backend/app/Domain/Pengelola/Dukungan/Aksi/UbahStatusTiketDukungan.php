<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Layanan\PenulisPesanTiket;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Pengelola\Bersama\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Tim internal mengubah status tiket mengikuti state machine P-09. `Selesai` → `Ditangani` (buka lagi) hanya dalam
 * 7 hari; `Ditutup` final. Perubahan tampil di percakapan tenant sebagai pesan sistem.
 */
final class UbahStatusTiketDukungan
{
    public function __construct(
        private readonly KonteksPengelola $konteks,
        private readonly PenulisPesanTiket $penulisPesan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, string $uuidTiket, StatusTiketDukungan $tujuan, ?string $alasan = null): TiketDukungan
    {
        return $this->konteks->JalankanLintasTenant("Mengubah status tiket dukungan {$uuidTiket}", fn (): TiketDukungan => DB::transaction(
            function () use ($pelaku, $uuidTiket, $tujuan, $alasan): TiketDukungan {
                $tiket = $this->konteks->KueriLintasTenant(TiketDukungan::class)->where('Uuid', $uuidTiket)->lockForUpdate()->firstOrFail();
                $asal = $tiket->Status;

                if (! $asal->BisaBerubahKe($tujuan)) {
                    throw new PelanggaranAturanBisnis('P-09', "Tiket {$asal->AmbilLabel()} tidak bisa diubah menjadi {$tujuan->AmbilLabel()}.", 'Status');
                }

                if ($asal === StatusTiketDukungan::Selesai && $tujuan === StatusTiketDukungan::Ditangani && ! $tiket->CekBisaDibukaLagi()) {
                    $hari = (int) config('dukungan.HariBukaUlang');

                    throw new PelanggaranAturanBisnis('P-09', "Tiket selesai lebih dari {$hari} hari lalu dan tidak bisa dibuka lagi.", 'Status');
                }

                $tiket->Status = $tujuan;
                $tiket->IdPenanggungJawab ??= $tujuan === StatusTiketDukungan::Ditangani ? $pelaku->Id : null;
                $tiket->DiselesaikanPada = match ($tujuan) {
                    StatusTiketDukungan::Selesai => now(),
                    StatusTiketDukungan::Ditutup => $tiket->DiselesaikanPada,
                    default => null,
                };
                $tiket->DitutupPada = $tujuan === StatusTiketDukungan::Ditutup ? now() : null;
                $tiket->save();

                $this->penulisPesan->TulisSistem($tiket, "Status tiket diubah dari {$asal->AmbilLabel()} menjadi {$tujuan->AmbilLabel()}.");

                if ($alasan !== null && $alasan !== '') {
                    $this->penulisPesan->TulisSistem($tiket, "Alasan perubahan status oleh {$pelaku->Nama}: {$alasan}", catatanInternal: true);
                }

                $this->audit->Catat(
                    'dukungan.tiket.ubah-status',
                    $tiket,
                    nilaiLama: ['Status' => $asal->value],
                    nilaiBaru: ['Status' => $tujuan->value],
                    alasan: $alasan,
                    idPelaku: $pelaku->Id,
                    idTenant: $tiket->IdTenant,
                );

                return $tiket;
            },
        ));
    }
}
