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
 *
 * `biayaKirim` (F-17 bagian 3) = ongkir yang ditagih ke pembeli, **nominal** bukan persen karena datang dari tarif
 * kurir/jarak. `diskonKirim` (mis. promo gratis ongkir F-16c) dipisah supaya gratis ongkir tetap terlihat: ongkir yang
 * langsung ditulis nol tidak bisa dibedakan dari "tidak ada ongkir".
 */
final readonly class DataKalkulasi
{
    public BigDecimal $persenBiayaLayanan;

    public Uang $biayaKirim;

    public Uang $diskonKirim;

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
        ?Uang $biayaKirim = null,
        ?Uang $diskonKirim = null,
    ) {
        $this->biayaKirim = $biayaKirim ?? Uang::Nol();
        $this->diskonKirim = $diskonKirim ?? Uang::Nol();

        if ($this->biayaKirim->BernilaiNegatif() || $this->diskonKirim->BernilaiNegatif()) {
            throw new InvalidArgumentException('Biaya kirim dan diskon kirim tidak boleh negatif.');
        }

        // Diskon ongkir yang melebihi ongkirnya akan membuat ongkir netto negatif, dan itu bukan gratis ongkir
        // melainkan toko membayar pembeli untuk dikirimi barang.
        if ($this->diskonKirim->Bandingkan($this->biayaKirim) > 0) {
            throw new InvalidArgumentException("Diskon kirim {$this->diskonKirim->KeString()} melebihi biaya kirim {$this->biayaKirim->KeString()}.");
        }

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
