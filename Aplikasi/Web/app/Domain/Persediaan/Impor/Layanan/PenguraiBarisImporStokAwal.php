<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Katalog\Impor\Layanan\PenguraiNilaiImpor;
use App\Domain\Persediaan\Enum\BidangImporStokAwal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Mengurai satu baris berkas impor stok awal (DesainF05a C.7, fase per baris) menjadi data ternormalisasi +
 * galat per kolom, tanpa menyentuh database. Angka mengikuti aturan Indonesia: Stok lewat
 * `PenguraiNilaiImpor::UraiKuantitas` (≤ 4 desimal), Harga Modal lewat `PenguraiHppImpor` (≤ 6 desimal).
 * Pencocokan produk & lokasi, pelacakan, dan baris ganda diperiksa di fase berikutnya (`PemvalidasiImporStokAwal`).
 *
 * Data: `Sku`, `Barcode`, `NamaProduk`, `Lokasi` (teks|null), `Jumlah` (string skala 4|null), `HargaModal` (string
 * skala 6|null), `NomorBatch` (string|null), `TanggalKedaluwarsa` (Y-m-d|null), `NomorSeri` (list<string>).
 */
final class PenguraiBarisImporStokAwal
{
    /** Format tanggal kedaluwarsa yang diterima (sel tanggal Excel sudah menjadi Y-m-d). */
    private const FORMAT_TANGGAL = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'];

    public function __construct(private readonly PenguraiHppImpor $penguraiHpp) {}

    /**
     * @param  list<string>  $sel
     * @param  array<string, int|null>  $pemetaan
     * @return array{Data: array<string, mixed>, Galat: list<array{Bidang: string, Pesan: string}>}
     */
    public function Urai(array $sel, array $pemetaan): array
    {
        $ambil = function (BidangImporStokAwal $bidang) use ($sel, $pemetaan): string {
            $indeks = $pemetaan[$bidang->value] ?? null;

            return $indeks === null ? '' : trim($sel[$indeks] ?? '');
        };
        $teksAtauNull = fn (string $teks, int $maks): ?string => $teks === '' ? null : mb_substr($teks, 0, $maks);
        $galat = [];

        $data = [
            'Sku' => $teksAtauNull($ambil(BidangImporStokAwal::Sku), 64),
            'Barcode' => $teksAtauNull($ambil(BidangImporStokAwal::Barcode), 64),
            'NamaProduk' => $teksAtauNull($ambil(BidangImporStokAwal::NamaProduk), 150),
            'Lokasi' => $teksAtauNull($ambil(BidangImporStokAwal::Lokasi), 100),
            'Jumlah' => null,
            'HargaModal' => null,
            'NomorBatch' => null,
            'TanggalKedaluwarsa' => null,
            'NomorSeri' => [],
        ];

        if ($data['Sku'] === null && $data['Barcode'] === null && $data['NamaProduk'] === null) {
            $galat[] = self::Galat('Produk', 'Isi SKU, barcode, atau nama produk.');
        }

        $jumlah = $ambil(BidangImporStokAwal::Jumlah);

        try {
            $kuantitas = PenguraiNilaiImpor::UraiKuantitas($jumlah);

            if ($kuantitas === null) {
                $galat[] = self::Galat(BidangImporStokAwal::Jumlah->AmbilLabel(), 'Stok wajib diisi.');
            } elseif ($kuantitas->KeDesimal()->isZero()) {
                $galat[] = self::Galat(BidangImporStokAwal::Jumlah->AmbilLabel(), 'Stok harus lebih dari 0.');
            } else {
                $data['Jumlah'] = $kuantitas->KeString();
            }
        } catch (InvalidArgumentException $e) {
            $galat[] = self::Galat(BidangImporStokAwal::Jumlah->AmbilLabel(), $e->getMessage());
        }

        try {
            $hpp = $this->penguraiHpp->Urai($ambil(BidangImporStokAwal::HargaModal));

            if ($hpp === null) {
                $galat[] = self::Galat(BidangImporStokAwal::HargaModal->AmbilLabel(), 'Harga modal wajib diisi. Isi 0 bila barang tidak punya modal.');
            } else {
                $data['HargaModal'] = (string) $hpp;
            }
        } catch (InvalidArgumentException $e) {
            $galat[] = self::Galat(BidangImporStokAwal::HargaModal->AmbilLabel(), $e->getMessage());
        }

        $batch = $ambil(BidangImporStokAwal::NomorBatch);

        if (mb_strlen($batch) > 60) {
            $galat[] = self::Galat(BidangImporStokAwal::NomorBatch->AmbilLabel(), 'Nomor batch maksimal 60 karakter.');
        } else {
            $data['NomorBatch'] = $batch === '' ? null : $batch;
        }

        $kedaluwarsa = $ambil(BidangImporStokAwal::TanggalKedaluwarsa);

        if ($kedaluwarsa !== '') {
            $tanggal = self::UraiTanggal($kedaluwarsa);

            if ($tanggal === null) {
                $galat[] = self::Galat(BidangImporStokAwal::TanggalKedaluwarsa->AmbilLabel(), "Tanggal \"{$kedaluwarsa}\" tidak dikenali. Contoh: 2027-12-31 atau 31/12/2027.");
            } else {
                $data['TanggalKedaluwarsa'] = $tanggal;
            }
        }

        $data['NomorSeri'] = self::UraiNomorSeri($ambil(BidangImporStokAwal::NomorSeri));

        return ['Data' => $data, 'Galat' => $galat];
    }

    /** Tanggal kalender sah dalam salah satu `FORMAT_TANGGAL` → `Y-m-d`; selain itu null. */
    public static function UraiTanggal(string $teks): ?string
    {
        foreach (self::FORMAT_TANGGAL as $format) {
            try {
                $tanggal = CarbonImmutable::createFromFormat('!'.$format, $teks);
            } catch (Throwable) {
                continue;
            }

            if ($tanggal instanceof CarbonImmutable && $tanggal->format($format) === $teks && $tanggal->year >= 1900 && $tanggal->year <= 2999) {
                return $tanggal->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Nomor seri dipisah koma, titik koma, garis tegak, atau baris baru; spasi tepi dibuang, isi kosong diabaikan.
     * Duplikat dibiarkan agar pemeriksa pelacakan melaporkannya.
     *
     * @return list<string>
     */
    public static function UraiNomorSeri(string $teks): array
    {
        if ($teks === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;|\r\n]+/', $teks) ?: []), fn (string $nomor): bool => $nomor !== ''));
    }

    /**
     * @return array{Bidang: string, Pesan: string}
     */
    private static function Galat(string $bidang, string $pesan): array
    {
        return ['Bidang' => $bidang, 'Pesan' => $pesan];
    }
}
