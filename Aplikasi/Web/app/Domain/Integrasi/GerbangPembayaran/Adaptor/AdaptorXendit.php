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
 * Xendit QR Codes API (`POST /qr_codes`, api-version 2022-07-31, tipe DYNAMIC). Callback diverifikasi dengan header
 * `x-callback-token` = token verifikasi callback di dasbor Xendit. Mode ditentukan oleh jenis secret key
 * (development/production).
 */
final class AdaptorXendit extends AdaptorDasar
{
    private const ALAMAT = 'https://api.xendit.co';

    public function AmbilKode(): string
    {
        return 'Xendit';
    }

    private function Klien(): PendingRequest
    {
        return $this->Http()->withBasicAuth($this->Kredensial('KunciRahasia'), '')->withHeaders(['api-version' => '2022-07-31']);
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Kirim(fn () => $this->Klien()->get(self::ALAMAT.'/balance'));

        return $respons->successful()
            ? HasilUjiLayanan::Berhasil('Secret key diterima Xendit.')
            : HasilUjiLayanan::Gagal($this->Saring('Xendit menolak secret key: '.($respons->json('message') ?? "HTTP {$respons->status()}")));
    }

    public function BuatQris(PermintaanQris $permintaan): HasilQris
    {
        $respons = $this->Kirim(fn () => $this->Klien()->post(self::ALAMAT.'/qr_codes', [
            'reference_id' => $permintaan->nomorPesanan,
            'type' => 'DYNAMIC',
            'currency' => 'IDR',
            'amount' => $permintaan->AmbilJumlahBulat(),
            'expires_at' => $permintaan->kedaluwarsaPada->utc()->toIso8601ZuluString(),
            'metadata' => ['keterangan' => mb_substr($permintaan->keterangan, 0, 100)],
        ]));
        $qr = $respons->json('qr_string');

        if (! $respons->successful() || ! is_string($qr) || $qr === '') {
            throw $this->GagalRespons($respons, (string) ($respons->json('message') ?? 'QRIS tidak dibuat.'));
        }

        $kedaluwarsa = $respons->json('expires_at');

        return new HasilQris((string) $respons->json('id'), $qr, is_string($kedaluwarsa) ? CarbonImmutable::parse($kedaluwarsa)->utc() : $permintaan->kedaluwarsaPada);
    }

    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang
    {
        $respons = $this->Kirim(fn () => $this->Klien()->get(self::ALAMAT.'/qr_codes/'.rawurlencode($idReferensi).'/payments'));
        $daftar = $respons->json('data');

        foreach (is_array($daftar) ? $daftar : [] as $bayar) {
            if (is_array($bayar) && ($bayar['status'] ?? null) === 'SUCCEEDED') {
                return StatusPembayaranGerbang::Lunas;
            }
        }

        $qr = $this->Kirim(fn () => $this->Klien()->get(self::ALAMAT.'/qr_codes/'.rawurlencode($idReferensi)));

        return $qr->json('status') === 'INACTIVE' ? StatusPembayaranGerbang::Kedaluwarsa : StatusPembayaranGerbang::Menunggu;
    }

    public function UraiWebhook(Request $permintaan): ?HasilWebhook
    {
        $token = (string) $permintaan->header('x-callback-token');
        $harapan = $this->Kredensial('TokenCallback');

        if ($harapan === '' || ! hash_equals($harapan, $token)) {
            return null;
        }

        $data = $permintaan->input('data');
        $data = is_array($data) ? $data : [];
        $pesanan = (string) ($data['reference_id'] ?? '');

        if ($pesanan === '') {
            return null;
        }

        $status = match ($data['status'] ?? null) {
            'SUCCEEDED' => StatusPembayaranGerbang::Lunas,
            'FAILED' => StatusPembayaranGerbang::Gagal,
            default => StatusPembayaranGerbang::Menunggu,
        };

        return new HasilWebhook($pesanan, $status, isset($data['amount']) ? (string) $data['amount'] : null, isset($data['qr_id']) ? (string) $data['qr_id'] : null);
    }
}
