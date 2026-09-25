<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Enum;

use InvalidArgumentException;

/**
 * Jenis dokumen yang dinomori `PenomorDokumen` (`NomorUrutDokumen.JenisDokumen`, DesainF05a B.4). Nomor berurutan
 * tanpa celah per tenant, jenis, dan periode `YYYY-MM`: `SA/2026/09/0001`, `JU/2026/09/000001`.
 */
enum JenisDokumenBernomor: string
{
    case StokAwal = 'StokAwal';
    case Jurnal = 'Jurnal';
    // F-13a: transaksi kas & bank back-office `KB/2026/09/0001`.
    case TransaksiKasBank = 'TransaksiKasBank';
    // F-05b: nomor berkode lokasi stok (`TF/{ASAL}-{TUJUAN}/{YYMM}/{SEQ4}`, `SO/{LOKASI}/{YYMM}/{SEQ3}`,
    // `PS/{LOKASI}/{YYMM}/{SEQ4}`) disusun `PenomorDokumenPersediaan`; urutnya per tenant, jenis, dan periode.
    case TransferStok = 'TransferStok';
    case StokOpname = 'StokOpname';
    case PenyesuaianStok = 'PenyesuaianStok';
    // F-04 fase 1: nomor pembelian disusun `PenomorPembelian` (`PO/{OUTLET}/{YYMM}/{SEQ4}`, `GR/…`, `RB/…` per outlet;
    // `FB/{YYMM}/{SEQ4}`, `BH/{YYMM}/{SEQ4}` per tenant); urutnya per tenant, jenis, periode (dan outlet).
    case PesananPembelian = 'PesananPembelian';
    case PenerimaanBarang = 'PenerimaanBarang';
    case FakturPembelian = 'FakturPembelian';
    case PembayaranHutang = 'PembayaranHutang';
    case ReturPembelian = 'ReturPembelian';

    public function AmbilAwalan(): string
    {
        return match ($this) {
            self::StokAwal => 'SA',
            self::Jurnal => 'JU',
            self::TransaksiKasBank => 'KB',
            self::TransferStok => 'TF',
            self::StokOpname => 'SO',
            self::PenyesuaianStok => 'PS',
            self::PesananPembelian => 'PO',
            self::PenerimaanBarang => 'GR',
            self::FakturPembelian => 'FB',
            self::PembayaranHutang => 'BH',
            self::ReturPembelian => 'RB',
        };
    }

    public function AmbilPanjangUrut(): int
    {
        return match ($this) {
            self::StokAwal, self::TransaksiKasBank, self::TransferStok, self::PenyesuaianStok => 4,
            self::StokOpname => 3,
            self::Jurnal => 6,
            self::PesananPembelian, self::PenerimaanBarang, self::FakturPembelian, self::PembayaranHutang, self::ReturPembelian => 4,
        };
    }

    /**
     * @param  string  $periode  `YYYY-MM`
     *
     * @throws InvalidArgumentException bila periode tidak berformat `YYYY-MM` atau urut < 1
     */
    public function FormatNomor(string $periode, int $urut): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $periode, $cocok) !== 1 || $urut < 1) {
            throw new InvalidArgumentException("Periode {$periode} atau nomor urut {$urut} tidak valid.");
        }

        return sprintf('%s/%s/%s/%s', $this->AmbilAwalan(), $cocok[1], $cocok[2], str_pad((string) $urut, $this->AmbilPanjangUrut(), '0', STR_PAD_LEFT));
    }
}
