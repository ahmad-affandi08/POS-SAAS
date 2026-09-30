<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Penjualan\Data\DataBarisFakturPajak;
use App\Domain\Penjualan\Data\DataFakturPajak;
use App\Domain\Penjualan\Data\HasilFakturPajakCoretax;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use XMLWriter;

/**
 * Menulis XML impor massal Faktur Pajak Keluaran Coretax (`TaxInvoiceBulk`, PRD v3.12).
 *
 * Nama elemen mengikuti contoh berkas DJP (v1.4) karena DJP tidak menerbitkan XSD. Nama elemen berbahasa Inggris di
 * sini **bukan** pelanggaran konvensi penamaan: itu kontrak format pihak ketiga (§13.7.4). `XMLWriter` meloloskan
 * karakter khusus, dan karakter kontrol yang tidak sah di XML 1.0 dibuang dari teks bebas.
 */
final class PenulisXmlCoretax
{
    public function Tulis(HasilFakturPajakCoretax $hasil): string
    {
        if (! $hasil->BisaDiekspor()) {
            throw new InvalidArgumentException('Tidak ada faktur yang siap diekspor.');
        }

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('TaxInvoiceBulk');
        $xml->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->Elemen($xml, 'TIN', $hasil->tinPenjual);
        $xml->startElement('ListOfTaxInvoice');

        foreach ($hasil->faktur as $faktur) {
            $this->TulisFaktur($xml, $faktur, $hasil->tinPenjual);
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function TulisFaktur(XMLWriter $xml, DataFakturPajak $faktur, string $tinPenjual): void
    {
        $xml->startElement('TaxInvoice');
        $this->Elemen($xml, 'TaxInvoiceDate', $faktur->tanggal);
        $this->Elemen($xml, 'TaxInvoiceOpt', 'Normal');
        $this->Elemen($xml, 'TrxCode', '04');
        $this->Elemen($xml, 'AddInfo', '');
        $this->Elemen($xml, 'CustomDoc', '');
        $this->Elemen($xml, 'RefDesc', $faktur->nomorFaktur);
        $this->Elemen($xml, 'FacilityStamp', '');
        $this->Elemen($xml, 'SellerIDTKU', $tinPenjual.'000000');
        $this->Elemen($xml, 'BuyerTin', $faktur->tinPembeli);
        $this->Elemen($xml, 'BuyerDocument', $faktur->jenisDokumenPembeli);
        $this->Elemen($xml, 'BuyerCountry', 'IDN');
        $this->Elemen($xml, 'BuyerDocumentNumber', $faktur->nomorDokumenPembeli);
        $this->Elemen($xml, 'BuyerName', $faktur->namaPembeli);
        $this->Elemen($xml, 'BuyerAdress', $faktur->alamatPembeli);
        $this->Elemen($xml, 'BuyerEmail', $faktur->emailPembeli ?? '');
        $this->Elemen($xml, 'BuyerIDTKU', $faktur->idTkuPembeli);
        $xml->startElement('ListOfGoodService');

        foreach ($faktur->baris as $baris) {
            $this->TulisBaris($xml, $baris);
        }

        $xml->endElement();
        $xml->endElement();
    }

    private function TulisBaris(XMLWriter $xml, DataBarisFakturPajak $b): void
    {
        $xml->startElement('GoodService');
        $this->Elemen($xml, 'Opt', $b->opsi);
        $this->Elemen($xml, 'Code', $b->kode);
        $this->Elemen($xml, 'Name', $b->nama);
        $this->Elemen($xml, 'Unit', $b->satuan);
        $this->Elemen($xml, 'Price', self::Angka($b->harga));
        $this->Elemen($xml, 'Qty', self::Angka($b->jumlah));
        $this->Elemen($xml, 'TotalDiscount', self::Angka($b->totalDiskon));
        $this->Elemen($xml, 'TaxBase', self::Angka($b->dpp));
        $this->Elemen($xml, 'OtherTaxBase', self::Angka($b->dppNilaiLain));
        $this->Elemen($xml, 'VATRate', self::Angka($b->tarifPpn));
        $this->Elemen($xml, 'VAT', self::Angka($b->ppn));
        $this->Elemen($xml, 'STLGRate', '0');
        $this->Elemen($xml, 'STLG', '0');
        $xml->endElement();
    }

    private function Elemen(XMLWriter $xml, string $nama, string $isi): void
    {
        $xml->startElement($nama);
        $xml->text(self::BersihkanTeks($isi));
        $xml->endElement();
    }

    /** Angka tanpa nol di belakang koma yang tidak perlu (`100000.00` → `100000`). */
    private static function Angka(string $nilai): string
    {
        $angka = BigDecimal::of($nilai);

        return $angka->hasNonZeroFractionalPart() ? (string) $angka->stripTrailingZeros() : (string) $angka->toScale(0);
    }

    private static function BersihkanTeks(string $teks): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $teks) ?? '';
    }
}
