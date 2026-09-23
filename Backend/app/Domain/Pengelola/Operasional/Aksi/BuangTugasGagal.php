<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Operasional\Kueri\TugasGagal;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * Membuang job gagal yang tidak perlu dicoba ulang (P-11), wajib dengan alasan. Tercatat di log audit.
 */
final class BuangTugasGagal
{
    public function __construct(
        private readonly TugasGagal $tugasGagal,
        private readonly FailedJobProviderInterface $penyediaTugasGagal,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, string $uuid, string $alasan): void
    {
        $detail = $this->tugasGagal->AmbilDetail($uuid)
            ?? throw new PelanggaranAturanBisnis('P-11', 'Job gagal ini sudah tidak ada. Mungkin sudah dicoba ulang atau dibuang.');

        $this->penyediaTugasGagal->forget($uuid);

        $this->audit->Catat(
            'operasional.job-gagal.buang',
            nilaiLama: ['Uuid' => $uuid, 'NamaTugas' => $detail['NamaTugas'], 'Antrean' => $detail['Antrean']],
            alasan: $alasan,
            idPelaku: $pelaku->Id,
        );
    }
}
