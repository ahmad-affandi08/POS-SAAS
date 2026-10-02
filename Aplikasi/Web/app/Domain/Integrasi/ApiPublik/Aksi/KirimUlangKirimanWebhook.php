<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Tugas\KirimWebhookTugas;

/**
 * X7 bagian 2 (PRD §16.4 tombol "kirim ulang"): kiriman Gagal atau Terkirim dijadwalkan lagi dengan muatan dan
 * `IdPeristiwa` yang sama (penerima yang sudah memprosesnya bisa mengabaikan lewat dedup). Hitungan percobaan mulai dari
 * nol. Webhook harus masih aktif.
 */
final class KirimUlangKirimanWebhook
{
    public function Jalankan(KirimanWebhook $kiriman): void
    {
        $webhook = $kiriman->Webhook;

        if ($webhook->trashed() || ! $webhook->Aktif) {
            throw new PelanggaranAturanBisnis('WebhookNonaktif', 'Aktifkan webhook dulu sebelum mengirim ulang.');
        }

        if ($kiriman->Status === StatusKirimanWebhook::Menunggu) {
            throw new PelanggaranAturanBisnis('KirimanMasihMenunggu', 'Kiriman ini masih dalam antrean coba ulang.');
        }

        $kiriman->forceFill(['Status' => StatusKirimanWebhook::Menunggu, 'Percobaan' => 0, 'BerikutnyaPada' => now()])->save();
        KirimWebhookTugas::dispatch($kiriman->IdTenant, $kiriman->Id);
    }
}
