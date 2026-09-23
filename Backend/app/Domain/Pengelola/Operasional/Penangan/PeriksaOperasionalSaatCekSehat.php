<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Penangan;

use App\Domain\Pengelola\Operasional\Aksi\PeriksaKondisiOperasional;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * BR-P11.1: `/sehat` dipanggil uptime monitor eksternal tiap menit (§20.3). Setiap panggilan (paling sering sekali per
 * 30 detik) ikut memeriksa alert operasional, sehingga scheduler yang mati tetap memicu email ke Teknis.
 * Tidak pernah menggagalkan `/sehat`: kesehatan HTTP dan kesehatan scheduler dilaporkan terpisah.
 */
final class PeriksaOperasionalSaatCekSehat
{
    public const DETIK_JEDA = 30;

    public function __construct(private readonly PeriksaKondisiOperasional $periksa) {}

    public function handle(DiagnosingHealth $peristiwa): void
    {
        try {
            if (Cache::add('operasional:sehat-diperiksa', true, self::DETIK_JEDA)) {
                $this->periksa->Jalankan();
            }
        } catch (Throwable $galat) {
            Log::error('Pemeriksaan operasional dari /sehat gagal.', ['Pesan' => Str::limit($galat->getMessage(), 300)]);
        }
    }
}
