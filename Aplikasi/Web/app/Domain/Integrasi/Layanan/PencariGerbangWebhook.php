<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Layanan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\GerbangPembayaran\GerbangTenant;
use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;

/**
 * Menemukan gerbang tenant dari URL webhook `/webhook/{penyedia}/{tokenWebhook}` (F-08, v2.06). Seperti
 * `PerangkatBerdasarkanToken`, bagian `IdTenant` (basis-36) di token hanya menetapkan scope pencarian; gerbang
 * ditemukan hanya bila tokennya cocok persis di tenant itu (tanpa query lintas tenant). Penyedia di URL harus sama
 * dengan penyedia gerbang tenant yang aktif. Gagal = tenant aktif dikosongkan, null (dijawab 404).
 */
final class PencariGerbangWebhook
{
    public const POLA_TOKEN = '[0-9a-z]{1,13}-[A-Za-z0-9]{40}';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PembuatGerbangPembayaran $pembuat,
    ) {}

    public function Cari(string $kodePenyedia, string $token): ?GerbangTenant
    {
        $penyedia = PenyediaGerbang::DariKodeUrl($kodePenyedia);

        if ($penyedia === null || preg_match('/^([0-9a-z]{1,13})-[A-Za-z0-9]{40}$/', $token, $cocok) !== 1) {
            return null;
        }

        $idTenant = (int) base_convert($cocok[1], 36, 10);

        if ($idTenant <= 0) {
            return null;
        }

        $this->konteks->Atur($idTenant);
        $baris = GerbangPembayaranTenant::query()->where('TokenWebhook', $token)->first();
        $adaptor = $baris !== null && hash_equals($baris->TokenWebhook, $token) && $baris->Penyedia === $penyedia && $baris->Aktif
            ? $this->pembuat->BuatDariTenant($baris)
            : null;

        if ($baris === null || $adaptor === null) {
            $this->konteks->Kosongkan();

            return null;
        }

        return new GerbangTenant($adaptor, PembuatGerbangPembayaran::BuatUrlWebhook($penyedia, $baris->TokenWebhook), $idTenant);
    }

    /** Kesehatan webhook untuk Platform Pengelola: waktu notifikasi sah / tanda tangan salah terakhir. */
    public function CatatNotifikasi(bool $sah): void
    {
        GerbangPembayaranTenant::query()->toBase()->update([$sah ? 'WebhookDiterimaPada' : 'WebhookDitolakPada' => now()]);
    }
}
