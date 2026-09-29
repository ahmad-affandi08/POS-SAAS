<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Status dokumen grosir yang **langsung diposting** saat disimpan (F-12, §9.7): surat jalan (BR-12.2), faktur penjualan
 * (BR-12.4), dan retur grosir (BR-12.7). Diposting → Dibatalkan lewat dokumen pembalik; Dibatalkan final (CLAUDE.md #8).
 *
 * Satu enum untuk ketiganya, cermin `StatusDokumenPembelian` di sisi beli. Sebelumnya `SuratJalan` dan
 * `FakturPenjualan` punya enum masing-masing yang isinya identik, dan retur akan menjadi salinan ketiga — tiga salinan
 * dari aturan yang sama adalah tiga tempat yang bisa menyimpang sendiri.
 *
 * **Status pembayaran tidak ada di sini.** Sisa tagihan, umur, dan pelunasan adalah milik `Piutang` (BR-12.5);
 * menyalinnya ke dokumen berarti dua sumber kebenaran yang bisa berbeda begitu pelunasan dibatalkan.
 */
enum StatusDokumenGrosir: string
{
    case Diposting = 'Diposting';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this === self::Diposting && $tujuan === self::Dibatalkan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diposting => 'Diposting',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
