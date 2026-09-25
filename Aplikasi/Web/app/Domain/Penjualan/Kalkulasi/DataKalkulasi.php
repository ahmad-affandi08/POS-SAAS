<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Masukan mesin kalkulasi penjualan F-07a (PRD "Rincian F-07a"): pengaturan dokumen, pajak dokumen, baris, potongan
 * tingkat pesanan (promo pesanan + diskon manual pesanan; persen dari Subtotal), dan pembayaran.
 *
 * `tukarPoin` (F-16b, J-16.4) = nilai Rupiah penukaran poin loyalti: potongan pesanan sebelum pajak yang diterapkan
 * setelah potongan pesanan lain dan dibatasi sisa Subtotal.
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
        public ?Uang $tukarPoin = null,
    ) {
        $this->persenBiayaLayanan = BigDecimal::of($persenBiayaLayanan);

        if ($this->persenBiayaLayanan->isNegative() || $this->persenBiayaLayanan->isGreaterThan(100)) {
            throw new InvalidArgumentException("Persen biaya layanan harus 0 sampai 100: {$this->persenBiayaLayanan}");
        }

        if ($tukarPoin?->BernilaiNegatif()) {
            throw new InvalidArgumentException("Nilai tukar poin tidak boleh negatif: {$tukarPoin->KeString()}");
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
