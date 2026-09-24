<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Satu jenis pajak dokumen untuk mesin kalkulasi F-07a: kode unik (misal `PPN`, `PB1`), tarif dalam persen (dari
 * `TarifPajak` bertanggal berlaku, tidak pernah di-hard-code), pengali DPP `pembilang/penyebut` (DPP nilai lain,
 * misal 11/12; bawaan 1/1), dan dasar pengenaan (`SubtotalPlusLayanan` = biaya layanan ikut DPP).
 */
final readonly class DataPajakKalkulasi
{
    public BigDecimal $tarif;

    public function __construct(
        public string $kode,
        BigDecimal|int|string $tarif,
        public DasarPengenaanPajak $dasarPengenaan = DasarPengenaanPajak::Subtotal,
        public int $pengaliDppPembilang = 1,
        public int $pengaliDppPenyebut = 1,
    ) {
        if (trim($kode) === '') {
            throw new InvalidArgumentException('Kode pajak wajib diisi.');
        }

        $this->tarif = BigDecimal::of($tarif);

        if ($this->tarif->isNegative() || $this->tarif->isGreaterThan(100)) {
            throw new InvalidArgumentException("Tarif pajak {$kode} harus 0 sampai 100 persen: {$this->tarif}");
        }

        if ($pengaliDppPembilang <= 0 || $pengaliDppPenyebut <= 0) {
            throw new InvalidArgumentException("Pengali DPP pajak {$kode} harus pecahan positif.");
        }
    }
}
