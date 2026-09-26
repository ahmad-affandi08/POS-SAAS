<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

use App\Domain\Integrasi\HasilUjiLayanan;
use Illuminate\Http\Request;

/**
 * Port gerbang pembayaran (PRD §16.6, F-08 QRIS dinamis). Penyedia dipilih di konsol Platform Pengelola (P-05);
 * adaptor tidak menyimpan state dan tidak pernah mencatat kredensial.
 */
interface GerbangPembayaran
{
    public function AmbilKode(): string;

    /** Tes kredensial tanpa membuat transaksi. */
    public function UjiKoneksi(): HasilUjiLayanan;

    /** @throws GalatGerbang */
    public function BuatQris(PermintaanQris $permintaan): HasilQris;

    /** @throws GalatGerbang */
    public function CekStatus(string $nomorPesanan, string $idReferensi): StatusPembayaranGerbang;

    /**
     * Verifikasi & urai notifikasi masuk. Null = tanda tangan/token tidak sah (webhook diabaikan, dijawab 401).
     */
    public function UraiWebhook(Request $permintaan): ?HasilWebhook;
}
