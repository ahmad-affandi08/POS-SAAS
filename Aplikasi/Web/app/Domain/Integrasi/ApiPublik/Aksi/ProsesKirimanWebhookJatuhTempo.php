<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Tugas\KirimWebhookTugas;

/**
 * X7 bagian 2: untuk tenant aktif di konteks, antrekan kiriman webhook `Menunggu` yang jadwal coba ulangnya sudah lewat
 * (termasuk kiriman pertama yang tugas antreannya hilang), lalu hapus log kiriman selesai yang lebih tua dari
 * [HARI_RETENSI] hari.
 */
final class ProsesKirimanWebhookJatuhTempo
{
    public const BATAS_PER_TENANT = 200;

    public const HARI_RETENSI = 30;

    /** @return int jumlah kiriman yang diantrekan */
    public function Jalankan(int $idTenant): int
    {
        $id = KirimanWebhook::query()
            ->where('Status', StatusKirimanWebhook::Menunggu->value)
            ->where('BerikutnyaPada', '<=', now())
            ->orderBy('BerikutnyaPada')
            ->limit(self::BATAS_PER_TENANT)
            ->pluck('Id');

        foreach ($id as $idKiriman) {
            KirimWebhookTugas::dispatch($idTenant, (int) $idKiriman);
        }

        KirimanWebhook::query()
            ->whereIn('Status', [StatusKirimanWebhook::Terkirim->value, StatusKirimanWebhook::Gagal->value])
            ->where('DibuatPada', '<', now()->subDays(self::HARI_RETENSI))
            ->limit(1000)
            ->delete();

        return $id->count();
    }
}
