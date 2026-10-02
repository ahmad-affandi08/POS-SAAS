<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Kueri;

use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;

/** Halaman Pengaturan › Webhook: daftar webhook (tanpa rahasia) dan 100 kiriman terakhir (tanpa muatan). */
final class DaftarWebhook
{
    public const BATAS_KIRIMAN = 100;

    /**
     * @return list<array{Uuid: string, Nama: string, Url: string, Peristiwa: list<string>, Aktif: bool, DibuatPada: string|null}>
     */
    public function Ambil(): array
    {
        return array_values(WebhookTenant::query()->orderByDesc('Id')->get()
            ->map(fn (WebhookTenant $w): array => [
                'Uuid' => $w->Uuid,
                'Nama' => $w->Nama,
                'Url' => $w->Url,
                'Peristiwa' => $w->Peristiwa,
                'Aktif' => $w->Aktif,
                'DibuatPada' => $w->DibuatPada?->toIso8601ZuluString(),
            ])->all());
    }

    /**
     * @return list<array{Uuid: string, NamaWebhook: string, Peristiwa: string, Status: string, Percobaan: int, KodeRespons: int|null, CuplikanRespons: string|null, BerikutnyaPada: string|null, TerkirimPada: string|null, DibuatPada: string|null, BisaKirimUlang: bool}>
     */
    public function AmbilKiriman(): array
    {
        return array_values(KirimanWebhook::query()->with('Webhook')->orderByDesc('Id')->limit(self::BATAS_KIRIMAN)->get()
            ->map(fn (KirimanWebhook $k): array => [
                'Uuid' => $k->Uuid,
                'NamaWebhook' => $k->Webhook->Nama,
                'Peristiwa' => $k->Peristiwa,
                'Status' => $k->Status->value,
                'Percobaan' => $k->Percobaan,
                'KodeRespons' => $k->KodeRespons,
                'CuplikanRespons' => $k->CuplikanRespons,
                'BerikutnyaPada' => $k->BerikutnyaPada?->toIso8601ZuluString(),
                'TerkirimPada' => $k->TerkirimPada?->toIso8601ZuluString(),
                'DibuatPada' => $k->DibuatPada?->toIso8601ZuluString(),
                'BisaKirimUlang' => $k->Status->value !== 'Menunggu' && ! $k->Webhook->trashed() && $k->Webhook->Aktif,
            ])->all());
    }

    /** @return list<array{Nilai: string, Label: string}> */
    public static function AmbilOpsiPeristiwa(): array
    {
        return array_map(fn (PeristiwaWebhook $p): array => ['Nilai' => $p->value, 'Label' => $p->AmbilLabel()], PeristiwaWebhook::cases());
    }
}
