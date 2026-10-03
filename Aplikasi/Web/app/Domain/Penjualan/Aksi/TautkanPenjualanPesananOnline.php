<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananOnline;

/**
 * Konfirmasi tautan pesanan online ↔ penjualan POS (endpoint `pesanan-online/{uuid}/tautkan`, dipertahankan untuk
 * kompatibilitas kontrak API POS). Tautan **hanya dibuat oleh sinkron `Penjualan.Buat` ber-`UuidPesananOnline`**
 * (`PenutupUangMukaPesananOnline::Tandai`: kunci baris, uang muka terpakai, riwayat status). Endpoint ini tidak lagi
 * menautkan penjualan sembarang (dulu: penjualan Rp 5.000 bisa menutup pesanan Rp 500.000, satu penjualan menutup banyak
 * pesanan, uang muka terpakai tidak tercatat); ia hanya memastikan tautan yang sudah ada dan idempoten.
 */
final class TautkanPenjualanPesananOnline
{
    public function Jalankan(int $idOutlet, string $uuidPesanan, string $uuidPenjualan): PesananOnline
    {
        $pesanan = PesananOnline::query()->where('Uuid', $uuidPesanan)->where('IdOutlet', $idOutlet)->firstOrFail();
        $penjualan = Penjualan::query()->where('Uuid', $uuidPenjualan)->where('IdOutlet', $idOutlet)->firstOrFail();

        if ($pesanan->IdPenjualan === $penjualan->Id) {
            return $pesanan;
        }

        if ($pesanan->IdPenjualan !== null) {
            throw new PelanggaranAturanBisnis('SudahDitautkan', 'Pesanan sudah ditautkan ke penjualan lain.', 'UuidPenjualan', 409);
        }

        throw new PelanggaranAturanBisnis(
            'TautkanLewatSinkron',
            'Pesanan online ditautkan otomatis saat penjualan dengan UuidPesananOnline tersinkron. Tagih pesanan dari layar Pesanan online di kasir.',
            'UuidPenjualan',
            409,
        );
    }
}
