<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Persediaan\Enum\BidangImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;

/**
 * Pemetaan kolom impor stok awal (DesainF05a C.7): pemetaan otomatis dari judul kolom berkas (judul templat
 * `BidangImporStokAwal::AmbilLabel()` dan sinonim umum, tanpa membedakan huruf besar/kecil), keterangan & bidang
 * wajib untuk layar pemetaan, serta opsi pembaca berkas yang ditetapkan saat unggah.
 */
final class PemetaKolomImporStokAwal
{
    /** Sinonim judul kolom (huruf kecil) selain label templat. */
    private const SINONIM = [
        'Sku' => ['sku', 'kode produk', 'kode barang', 'kode sku'],
        'Barcode' => ['barcode', 'kode batang', 'ean', 'upc'],
        'NamaProduk' => ['nama produk', 'nama barang', 'nama', 'produk', 'barang'],
        'Lokasi' => ['lokasi stok', 'lokasi', 'gudang', 'kode lokasi', 'kode gudang'],
        'Jumlah' => ['stok', 'stok awal', 'jumlah', 'qty', 'kuantitas', 'jumlah stok'],
        'HargaModal' => ['harga modal', 'hpp', 'hpp satuan', 'harga pokok', 'modal', 'harga beli'],
        'NomorBatch' => ['nomor batch', 'no batch', 'no. batch', 'batch', 'lot'],
        'TanggalKedaluwarsa' => ['kedaluwarsa', 'tanggal kedaluwarsa', 'kadaluarsa', 'tanggal kadaluarsa', 'expired', 'exp', 'ed'],
        'NomorSeri' => ['nomor seri', 'no seri', 'no. seri', 'serial', 'serial number', 'sn', 'imei'],
    ];

    /**
     * @param  list<array{Indeks: int, Judul: string, Contoh: list<string>}>  $kolomSumber
     * @return array<string, int|null> kunci = BidangImporStokAwal
     */
    public function Petakan(array $kolomSumber): array
    {
        $hasil = [];
        $dipakai = [];

        foreach (BidangImporStokAwal::cases() as $bidang) {
            $hasil[$bidang->value] = null;
            $kandidat = [mb_strtolower($bidang->AmbilLabel()), ...self::SINONIM[$bidang->value]];

            foreach ($kandidat as $judul) {
                foreach ($kolomSumber as $kolom) {
                    if (! isset($dipakai[$kolom['Indeks']]) && self::Normalkan($kolom['Judul']) === $judul) {
                        $hasil[$bidang->value] = $kolom['Indeks'];
                        $dipakai[$kolom['Indeks']] = true;
                        break 2;
                    }
                }
            }
        }

        return $hasil;
    }

    public static function CekWajib(BidangImporStokAwal $bidang): bool
    {
        return in_array($bidang, [BidangImporStokAwal::Jumlah, BidangImporStokAwal::HargaModal], true);
    }

    public static function AmbilKeterangan(BidangImporStokAwal $bidang): string
    {
        return match ($bidang) {
            BidangImporStokAwal::Sku => 'Pencocok produk utama. Isi SKU, barcode, atau nama produk (minimal satu).',
            BidangImporStokAwal::Barcode => 'Dipakai bila SKU kosong atau tidak dikenal.',
            BidangImporStokAwal::NamaProduk => 'Dipakai bila SKU dan barcode kosong. Nama harus persis sama.',
            BidangImporStokAwal::Lokasi => 'Kode atau nama lokasi stok. Kosong = lokasi stok bawaan.',
            BidangImporStokAwal::Jumlah => 'Jumlah dalam satuan dasar produk, lebih dari 0.',
            BidangImporStokAwal::HargaModal => 'HPP per satuan dasar, maksimal 6 angka di belakang koma. Isi 0 bila gratis.',
            BidangImporStokAwal::NomorBatch => 'Wajib untuk produk berpelacakan batch.',
            BidangImporStokAwal::TanggalKedaluwarsa => 'Tanggal kedaluwarsa batch, misal 2027-12-31 atau 31/12/2027.',
            BidangImporStokAwal::NomorSeri => 'Produk bernomor seri: pisahkan dengan koma, titik koma, atau baris baru. Jumlahnya harus sama dengan Stok.',
        };
    }

    /**
     * Opsi pembaca berkas yang ditetapkan saat unggah (baris judul, pemisah CSV).
     *
     * @return array{BarisJudul: int, PemisahCsv: string|null, Lembar: string|null}
     */
    public static function AmbilOpsiPembaca(ImporStokAwal $impor): array
    {
        $pembaca = (array) (($impor->Opsi ?? [])['Pembaca'] ?? []);

        return [
            'BarisJudul' => (int) ($pembaca['BarisJudul'] ?? 1),
            'PemisahCsv' => isset($pembaca['PemisahCsv']) && is_string($pembaca['PemisahCsv']) ? $pembaca['PemisahCsv'] : null,
            'Lembar' => isset($pembaca['Lembar']) && is_string($pembaca['Lembar']) ? $pembaca['Lembar'] : null,
        ];
    }

    /**
     * @return list<string>
     */
    public static function AmbilPeringatan(ImporStokAwal $impor): array
    {
        return array_values(array_map('strval', (array) (($impor->Opsi ?? [])['Peringatan'] ?? [])));
    }

    private static function Normalkan(string $judul): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($judul)) ?? '');
    }
}
