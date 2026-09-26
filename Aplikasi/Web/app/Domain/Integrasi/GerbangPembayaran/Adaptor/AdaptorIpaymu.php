<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran\Adaptor;

use App\Domain\Integrasi\GerbangPembayaran\HasilQris;
use App\Domain\Integrasi\GerbangPembayaran\HasilWebhook;
use App\Domain\Integrasi\GerbangPembayaran\PermintaanQris;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Integrasi\HasilUjiLayanan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * iPaymu API v2 direct payment QRIS (`/api/v2/payment/direct`). Setiap permintaan ditandatangani
 * HMAC-SHA256("POST:{VA}:{sha256(body) huruf kecil}:{API key}", API key). Notifikasi iPaymu tidak bertanda tangan,
 * jadi status selalu dikonfirmasi ulang lewat `/api/v2/transaction` sebelum dipercaya.
 */
final class AdaptorIpaymu extends AdaptorDasar
{
    public function AmbilKode(): string
    {
        return 'Ipaymu';
    }

    private function AlamatDasar(): string
    {
        return $this->CekSandbox() ? 'https://sandbox.ipaymu.com' : 'https://my.ipaymu.com';
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function Post(string $jalur, array $isi): Response
    {
        $json = (string) json_encode($isi, JSON_UNESCAPED_SLASHES);
        $va = $this->Pengaturan('NomorVa');
        $kunci = $this->Kredensial('KunciApi');
        $tanda = hash_hmac('sha256', 'POST:'.$va.':'.strtolower(hash('sha256', $json)).':'.$kunci, $kunci);

        return $this->Kirim(fn () => $this->Http()
            ->withHeaders(['va' => $va, 'signature' => $tanda, 'timestamp' => CarbonImmutable::now('Asia/Jakarta')->format('YmdHis')])
            ->withBody($json, 'application/json')
            ->post($this->AlamatDasar().$jalur));
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Post('/api/v2/balance', ['account' => $this->Pengaturan('NomorVa')]);

        return (int) $respons->json('Status') === 200
            ? HasilUjiLayanan::Berhasil('VA & API key diterima iPaymu.')
            : HasilUjiLayanan::Gagal($this->Saring('iPaymu menolak kredensial: '.($respons->json('Message') ?? "HTTP {$respons->status()}")));
    }

    public function BuatQris(PermintaanQris $permintaan): HasilQris
    {
        $jam = max(1, (int) ceil(CarbonImmutable::now()->diffInMinutes($permintaan->kedaluwarsaPada, true) / 60));
        $respons = $this->Post('/api/v2/payment/direct', [
            'name' => $permintaan->namaPelanggan,
            'phone' => $permintaan->teleponPelanggan,
            'email' => $permintaan->emailPelanggan,
            'amount' => $permintaan->AmbilJumlahBulat(),
            'notifyUrl' => $permintaan->urlNotifikasi,
            'referenceId' => $permintaan->nomorPesanan,
            'paymentMethod' => 'qris',
            'paymentChannel' => 'qris',
            'expired' => $jam,
            'expiredType' => 'hours',
            'comments' => mb_substr($permintaan->keterangan, 0, 100),
        ]);
        $qr = $respons->json('Data.QrString');

        if ((int) $respons->json('Status') !== 200 || ! is_string($qr) || $qr === '') {
            throw $this->GagalRespons($respons, (string) ($respons->json('Message') ?? 'QRIS tidak dibuat.'));
        }

        return new HasilQris((string) $respons->json('Data.TransactionId'), $qr, $permintaan->kedaluwarsaPada);
    }

    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang
    {
        $respons = $this->Post('/api/v2/transaction', ['transactionId' => $idReferensi]);

        return match ((int) $respons->json('Data.Status')) {
            1, 6 => StatusPembayaranGerbang::Lunas,
            -2 => StatusPembayaranGerbang::Kedaluwarsa,
            2, 3 => StatusPembayaranGerbang::Gagal,
            default => StatusPembayaranGerbang::Menunggu,
        };
    }

    public function UraiWebhook(Request $permintaan): ?HasilWebhook
    {
        $pesanan = (string) $permintaan->input('reference_id');
        $idTransaksi = (string) $permintaan->input('trx_id');

        if ($pesanan === '' || $idTransaksi === '') {
            return null;
        }

        // Tanpa tanda tangan: status dari notifikasi tidak dipercaya, konfirmasi ke iPaymu.
        return new HasilWebhook($pesanan, $this->CekStatus($pesanan, $idTransaksi), null, $idTransaksi);
    }
}
