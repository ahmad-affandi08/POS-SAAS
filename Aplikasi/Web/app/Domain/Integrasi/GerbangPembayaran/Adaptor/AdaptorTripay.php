<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran\Adaptor;

use App\Domain\Integrasi\GerbangPembayaran\HasilQris;
use App\Domain\Integrasi\GerbangPembayaran\HasilWebhook;
use App\Domain\Integrasi\GerbangPembayaran\PermintaanQris;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Integrasi\HasilUjiLayanan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;

/**
 * Tripay closed payment kanal QRIS (`POST /transaction/create`, signature HMAC-SHA256(kode merchant + merchant_ref
 * + amount, private key)). Callback diverifikasi dengan header `X-Callback-Signature` = HMAC-SHA256(body mentah,
 * private key).
 */
final class AdaptorTripay extends AdaptorDasar
{
    public function AmbilKode(): string
    {
        return 'Tripay';
    }

    private function AlamatDasar(): string
    {
        return $this->CekSandbox() ? 'https://tripay.co.id/api-sandbox' : 'https://tripay.co.id/api';
    }

    private function Klien(): PendingRequest
    {
        return $this->Http()->withToken($this->Kredensial('KunciApi'));
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Kirim(fn () => $this->Klien()->get($this->AlamatDasar().'/merchant/payment-channel'));

        return $respons->successful() && $respons->json('success') === true
            ? HasilUjiLayanan::Berhasil('API key diterima Tripay.')
            : HasilUjiLayanan::Gagal($this->Saring('Tripay menolak API key: '.($respons->json('message') ?? "HTTP {$respons->status()}")));
    }

    public function BuatQris(PermintaanQris $permintaan): HasilQris
    {
        $jumlah = $permintaan->AmbilJumlahBulat();
        $kode = $this->Pengaturan('KodeMerchant');
        $respons = $this->Kirim(fn () => $this->Klien()->post($this->AlamatDasar().'/transaction/create', [
            'method' => $this->Pengaturan('KanalQris') !== '' ? $this->Pengaturan('KanalQris') : 'QRIS',
            'merchant_ref' => $permintaan->nomorPesanan,
            'amount' => $jumlah,
            'customer_name' => $permintaan->namaPelanggan,
            'customer_email' => $permintaan->emailPelanggan,
            'customer_phone' => $permintaan->teleponPelanggan,
            'order_items' => [['name' => mb_substr($permintaan->keterangan, 0, 100), 'price' => $jumlah, 'quantity' => 1]],
            'callback_url' => $permintaan->urlNotifikasi,
            'expired_time' => $permintaan->kedaluwarsaPada->getTimestamp(),
            'signature' => hash_hmac('sha256', $kode.$permintaan->nomorPesanan.$jumlah, $this->Kredensial('KunciPrivat')),
        ]));
        $qr = $respons->json('data.qr_string');

        if ($respons->json('success') !== true || ! is_string($qr) || $qr === '') {
            throw $this->GagalRespons($respons, (string) ($respons->json('message') ?? 'QRIS tidak dibuat.'));
        }

        $kedaluwarsa = $respons->json('data.expired_time');

        return new HasilQris((string) $respons->json('data.reference'), $qr, is_int($kedaluwarsa) ? CarbonImmutable::createFromTimestampUTC($kedaluwarsa) : $permintaan->kedaluwarsaPada);
    }

    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang
    {
        $respons = $this->Kirim(fn () => $this->Klien()->get($this->AlamatDasar().'/transaction/detail', ['reference' => $idReferensi]));

        return self::PetakanStatus((string) $respons->json('data.status'));
    }

    public function UraiWebhook(Request $permintaan): ?HasilWebhook
    {
        $isi = $permintaan->getContent();
        $harapan = hash_hmac('sha256', $isi, $this->Kredensial('KunciPrivat'));

        if (! hash_equals($harapan, (string) $permintaan->header('X-Callback-Signature'))) {
            return null;
        }

        $data = json_decode($isi, true);

        if (! is_array($data) || ! isset($data['merchant_ref'])) {
            return null;
        }

        return new HasilWebhook(
            (string) $data['merchant_ref'],
            self::PetakanStatus((string) ($data['status'] ?? '')),
            isset($data['total_amount']) ? (string) $data['total_amount'] : null,
            isset($data['reference']) ? (string) $data['reference'] : null,
        );
    }

    private static function PetakanStatus(string $status): StatusPembayaranGerbang
    {
        return match ($status) {
            'PAID' => StatusPembayaranGerbang::Lunas,
            'EXPIRED' => StatusPembayaranGerbang::Kedaluwarsa,
            'FAILED', 'REFUND' => StatusPembayaranGerbang::Gagal,
            default => StatusPembayaranGerbang::Menunggu,
        };
    }
}
