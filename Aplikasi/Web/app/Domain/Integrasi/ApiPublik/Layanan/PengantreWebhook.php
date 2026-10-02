<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Layanan;

use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;
use App\Domain\Integrasi\ApiPublik\Tugas\KirimWebhookTugas;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Closure;
use Illuminate\Support\Str;

/**
 * Bekukan satu kejadian menjadi kiriman per webhook aktif yang melanggan `{IdPeristiwa, Peristiwa, TerjadiPada, Data}`
 * lalu antrekan pengirimannya (X7 §16.4). Hanya bila paket masih ber-fitur `api.publik`. Data disusun malas (hanya bila
 * ada pelanggan). Idempoten per (webhook, peristiwa, dokumen, kunci kejadian). Konteks tenant sudah diatur pemanggil.
 */
final class PengantreWebhook
{
    public function __construct(private readonly PemeriksaFiturTenant $fitur) {}

    /**
     * @param  Closure(): (array<string, mixed>|null)  $susunData
     */
    public function Antrekan(int $idTenant, PeristiwaWebhook $jenis, int $idDokumen, string $kunci, Closure $susunData): void
    {
        $webhook = WebhookTenant::query()->where('Aktif', true)->get()
            ->filter(fn (WebhookTenant $w): bool => $w->CekBerlangganan($jenis->value));

        if ($webhook->isEmpty() || ! $this->fitur->CekAktif($idTenant, 'api.publik')) {
            return;
        }

        $data = $susunData();

        if ($data === null) {
            return;
        }

        foreach ($webhook as $w) {
            $idPeristiwa = (string) Str::ulid();
            $kiriman = KirimanWebhook::query()->firstOrCreate(
                ['IdWebhookTenant' => $w->Id, 'Peristiwa' => $jenis->value, 'IdDokumen' => $idDokumen, 'KunciPeristiwa' => $kunci],
                [
                    'Uuid' => $idPeristiwa,
                    'Muatan' => ['IdPeristiwa' => $idPeristiwa, 'Peristiwa' => $jenis->value, 'TerjadiPada' => now()->toIso8601ZuluString(), 'Data' => $data],
                    'Status' => StatusKirimanWebhook::Menunggu,
                    'BerikutnyaPada' => now(),
                ],
            );

            if ($kiriman->wasRecentlyCreated) {
                KirimWebhookTugas::dispatch($idTenant, $kiriman->Id);
            }
        }
    }
}
