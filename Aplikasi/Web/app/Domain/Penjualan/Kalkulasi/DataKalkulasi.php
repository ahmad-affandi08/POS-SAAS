<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Masukan mesin kalkulasi penjualan F-07a (PRD "Rincian F-07a"): pengaturan dokumen, pajak dokumen, baris, potongan
 * tingkat pesanan (promo pesanan + diskon manual pesanan; persen dari Subtotal), dan pembayaran.
 */
final readonly class DataKalkulasi
{
    public BigDecimal $persenBiayaLayanan;

    /**
     * @param  list<DataBarisKalkulasi>  $baris
     * @param  list<DataPajakKalkulasi>  $pajak
     * @param  list<DataPotongan>  $potonganPesanan
     * @param  list<DataPembayaranKalkulasi>  $pembayaran
     */
    public function __construct(
        public bool $hargaTermasukPajak,
        public array $baris,
        public array $pajak = [],
        BigDecimal|int|string $persenBiayaLayanan = 0,
        public ?DataPembulatanTunai $pembulatanTunai = null,
        public array $potonganPesanan = [],
        public array $pembayaran = [],
    ) {
        $this->persenBiayaLayanan = BigDecimal::of($persenBiayaLayanan);

        if ($this->persenBiayaLayanan->isNegative() || $this->persenBiayaLayanan->isGreaterThan(100)) {
            throw new InvalidArgumentException("Persen biaya layanan harus 0 sampai 100: {$this->persenBiayaLayanan}");
        }

        $kodeDokumen = [];

        foreach ($pajak as $satuPajak) {
            if (isset($kodeDokumen[$satuPajak->kode])) {
                throw new InvalidArgumentException("Kode pajak dokumen ganda: {$satuPajak->kode}");
            }

            $kodeDokumen[$satuPajak->kode] = true;
        }

        foreach ($baris as $satuBaris) {
            foreach ($satuBaris->kodePajak ?? [] as $kode) {
                if (! isset($kodeDokumen[$kode])) {
                    throw new InvalidArgumentException("Pajak baris merujuk kode yang tidak ada di dokumen: {$kode}");
                }
            }
        }
    }
}
