<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Validasi batch/seri satu baris stok awal (DesainF05a C.4, H-3); dipakai Tim C (form & posting) dan Tim E (impor).
 * Murni (tanpa query): keunikan nomor seri di database diperiksa mesin buku stok (`NomorSeriSudahAda`).
 * - Batch: nomor batch wajib (≤ 60 karakter); kedaluwarsa wajib bila `persediaan.StokAwal.WajibKedaluwarsaBatch`.
 * - Seri: jumlah bilangan bulat = banyak nomor seri; nomor di-trim, 1–100 karakter, unik di baris (tanpa beda huruf
 *   besar/kecil, mengikuti kolasi database); maksimal `persediaan.StokAwal.MaksimalNomorSeriPerBaris`.
 * - Tanpa pelacakan: kolom batch, kedaluwarsa, dan nomor seri harus kosong.
 * Bidang galat relatif terhadap baris: `NomorBatch`, `TanggalKedaluwarsa`, `NomorSeri`, `Jumlah`.
 */
final class PemvalidasiPelacakan
{
    /**
     * @return list<array{Bidang: string, Pesan: string}>
     */
    public function PeriksaBaris(DataInfoProdukStok $produk, DataBarisStokAwal $baris): array
    {
        return match ($produk->pelacakan) {
            PelacakanProduk::Batch => $this->PeriksaBatch($produk, $baris),
            PelacakanProduk::Seri => $this->PeriksaSeri($produk, $baris),
            PelacakanProduk::Tidak => $this->PeriksaTanpaPelacakan($produk, $baris),
        };
    }

    /**
     * @return list<array{Bidang: string, Pesan: string}>
     */
    private function PeriksaBatch(DataInfoProdukStok $produk, DataBarisStokAwal $baris): array
    {
        $galat = [];
        $nomor = trim((string) $baris->nomorBatch);

        if ($nomor === '') {
            $galat[] = self::Galat('NomorBatch', "Isi nomor batch untuk {$produk->nama}.");
        } elseif (mb_strlen($nomor) > 60) {
            $galat[] = self::Galat('NomorBatch', 'Nomor batch maksimal 60 karakter.');
        }

        if ($baris->tanggalKedaluwarsa === null && (bool) config('persediaan.StokAwal.WajibKedaluwarsaBatch', true)) {
            $galat[] = self::Galat('TanggalKedaluwarsa', "Isi tanggal kedaluwarsa batch untuk {$produk->nama}.");
        }

        if ($baris->nomorSeri !== []) {
            $galat[] = self::Galat('NomorSeri', "{$produk->nama} dilacak per batch, bukan nomor seri. Kosongkan nomor seri.");
        }

        return $galat;
    }

    /**
     * @return list<array{Bidang: string, Pesan: string}>
     */
    private function PeriksaSeri(DataInfoProdukStok $produk, DataBarisStokAwal $baris): array
    {
        $galat = [];

        if (trim((string) $baris->nomorBatch) !== '' || $baris->tanggalKedaluwarsa !== null) {
            $galat[] = self::Galat('NomorBatch', "{$produk->nama} dilacak per nomor seri, bukan batch. Kosongkan nomor batch dan kedaluwarsa.");
        }

        $jumlah = $baris->jumlah->KeDesimal();

        if (! self::CekBulat($jumlah)) {
            $galat[] = self::Galat('Jumlah', 'Jumlah produk bernomor seri harus bilangan bulat.');

            return $galat;
        }

        $maksimal = (int) config('persediaan.StokAwal.MaksimalNomorSeriPerBaris', 1000);

        if (count($baris->nomorSeri) > $maksimal) {
            $galat[] = self::Galat('NomorSeri', "Maksimal {$maksimal} nomor seri per baris. Pecah menjadi beberapa dokumen stok awal.");

            return $galat;
        }

        $sudahAda = [];
        $ganda = [];
        $adaTidakValid = false;

        foreach ($baris->nomorSeri as $nomor) {
            $nomor = trim($nomor);

            if ($nomor === '' || mb_strlen($nomor) > 100) {
                $adaTidakValid = true;

                continue;
            }

            $kunci = mb_strtolower($nomor);

            if (isset($sudahAda[$kunci])) {
                $ganda[$kunci] = $nomor;
            }

            $sudahAda[$kunci] = true;
        }

        if ($adaTidakValid) {
            $galat[] = self::Galat('NomorSeri', 'Setiap nomor seri 1–100 karakter dan tidak boleh kosong.');
        }

        if ($ganda !== []) {
            $galat[] = self::Galat('NomorSeri', 'Nomor seri ganda di baris ini: '.implode(', ', array_values($ganda)).'.');
        }

        $banyak = count($baris->nomorSeri);

        if (! $jumlah->isEqualTo($banyak)) {
            $galat[] = self::Galat('NomorSeri', "Jumlah {$jumlah->strippedOfTrailingZeros()} tetapi nomor seri yang diisi {$banyak}. Isi satu nomor seri per unit.");
        }

        return $galat;
    }

    /**
     * @return list<array{Bidang: string, Pesan: string}>
     */
    private function PeriksaTanpaPelacakan(DataInfoProdukStok $produk, DataBarisStokAwal $baris): array
    {
        $galat = [];

        if (trim((string) $baris->nomorBatch) !== '' || $baris->tanggalKedaluwarsa !== null) {
            $galat[] = self::Galat('NomorBatch', "{$produk->nama} tidak dilacak per batch. Kosongkan nomor batch dan kedaluwarsa.");
        }

        if ($baris->nomorSeri !== []) {
            $galat[] = self::Galat('NomorSeri', "{$produk->nama} tidak dilacak per nomor seri. Kosongkan nomor seri.");
        }

        return $galat;
    }

    private static function CekBulat(BigDecimal $jumlah): bool
    {
        return $jumlah->toScale(0, RoundingMode::Down)->isEqualTo($jumlah);
    }

    /**
     * @return array{Bidang: string, Pesan: string}
     */
    private static function Galat(string $bidang, string $pesan): array
    {
        return ['Bidang' => $bidang, 'Pesan' => $pesan];
    }
}
