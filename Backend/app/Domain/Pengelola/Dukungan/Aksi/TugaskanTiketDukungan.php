<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Layanan\PenulisPesanTiket;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Mengambil (menugaskan ke diri sendiri) atau menugaskan tiket ke anggota lain yang berizin menangani tiket (P-09).
 * Tiket `Baru` otomatis menjadi `Ditangani`. Penugasan dicatat sebagai catatan internal dan di log audit.
 */
final class TugaskanTiketDukungan
{
    public function __construct(
        private readonly KonteksPengelola $konteks,
        private readonly PenulisPesanTiket $penulisPesan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, string $uuidTiket, PenggunaPengelola $penanggungJawab): TiketDukungan
    {
        if (! $penanggungJawab->Aktif || ! $penanggungJawab->PunyaIzin(IzinPengelola::DukunganTiketTangani)) {
            throw new PelanggaranAturanBisnis('P-09', "{$penanggungJawab->Nama} tidak berizin menangani tiket dukungan.", 'UuidPenanggungJawab');
        }

        return $this->konteks->JalankanLintasTenant("Menugaskan tiket dukungan {$uuidTiket}", fn (): TiketDukungan => DB::transaction(
            function () use ($pelaku, $uuidTiket, $penanggungJawab): TiketDukungan {
                $tiket = $this->konteks->KueriLintas(TiketDukungan::class)->where('Uuid', $uuidTiket)->lockForUpdate()->firstOrFail();

                if (! $tiket->Status->CekTerbuka()) {
                    throw new PelanggaranAturanBisnis('P-09', "Tiket berstatus {$tiket->Status->AmbilLabel()} tidak bisa ditugaskan. Buka lagi tiket lebih dulu.");
                }

                $lama = ['IdPenanggungJawab' => $tiket->IdPenanggungJawab, 'Status' => $tiket->Status->value];
                $tiket->IdPenanggungJawab = $penanggungJawab->Id;

                if ($tiket->Status === StatusTiketDukungan::Baru) {
                    $tiket->Status = StatusTiketDukungan::Ditangani;
                }

                $tiket->save();

                $this->penulisPesan->TulisSistem(
                    $tiket,
                    $pelaku->Id === $penanggungJawab->Id ? "{$pelaku->Nama} mengambil tiket ini." : "{$pelaku->Nama} menugaskan tiket ini ke {$penanggungJawab->Nama}.",
                    catatanInternal: true,
                );

                $this->audit->Catat(
                    'dukungan.tiket.tugaskan',
                    $tiket,
                    nilaiLama: $lama,
                    nilaiBaru: ['IdPenanggungJawab' => $tiket->IdPenanggungJawab, 'Status' => $tiket->Status->value],
                    idPelaku: $pelaku->Id,
                    idTenant: $tiket->IdTenant,
                );

                return $tiket;
            },
        ));
    }
}
