<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Operasional\Kueri\TugasGagal;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengembalikan job gagal ke antrean asalnya (P-11). Memakai `queue:retry` bawaan Laravel agar batas waktu coba
 * ulang job diperbarui dengan benar. Tercatat di log audit.
 */
final class CobaUlangTugasGagal
{
    public function __construct(
        private readonly TugasGagal $tugasGagal,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, string $uuid): void
    {
        $detail = $this->tugasGagal->AmbilDetail($uuid)
            ?? throw new PelanggaranAturanBisnis('P-11', 'Job gagal ini sudah tidak ada. Mungkin sudah dicoba ulang atau dibuang.');

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $galat) {
            // Misal kelas job sudah dihapus/diganti sejak job gagal: payload tidak bisa dibaca ulang.
            throw new PelanggaranAturanBisnis('P-11', 'Job ini tidak bisa dicoba ulang ('.Str::limit(TugasGagal::SaringRahasia($galat->getMessage()), 150).'). Buang job bila tidak diperlukan.');
        }

        $this->audit->Catat(
            'operasional.job-gagal.coba-ulang',
            nilaiLama: ['Uuid' => $uuid, 'NamaTugas' => $detail['NamaTugas'], 'Antrean' => $detail['Antrean']],
            idPelaku: $pelaku->Id,
        );
    }
}
