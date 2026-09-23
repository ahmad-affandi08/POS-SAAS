<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Harga\Data\DataBarisProdukHarga;
use App\Domain\Katalog\Harga\Data\DataDaftarHargaResolusi;
use App\Domain\Katalog\Harga\Data\DataKatalogHarga;
use App\Domain\Katalog\Harga\Data\DataPermintaanHarga;
use App\Domain\Katalog\Harga\Data\HasilHarga;
use App\Domain\Katalog\Harga\Enum\SumberHarga;
use InvalidArgumentException;

/**
 * Penentu harga satuan lapis 3–5 price engine F-03 (daftar harga → harga bertingkat → harga dasar). **Murni**: tanpa
 * DB, model, atau facade, sehingga identik dengan `PenentuHarga` Dart di `Paket/MesinKasir/lib/Harga/` dan diuji
 * dengan test vector bersama `Spesifikasi/VektorUjiKalkulasi/Harga/` (CLAUDE.md #18). Lapis 1 (harga manual kasir)
 * dan 2 (promo) milik F-07/F-16. Semua perbandingan desimal (brick/math).
 *
 * Algoritma (DesainF03 C.3):
 * 1. Daftar harga cocok bila aktif dan setiap kondisinya null atau sama dengan permintaan (outlet ∈ daftar, kanal,
 *    tier persis, `MulaiPada ≤ waktu < SelesaiPada`).
 * 2. Urut: `Prioritas` menurun, lalu jumlah kondisi terisi (outlet, kanal, tier, rentang waktu) menurun, lalu `Uuid`
 *    menaik (perbandingan byte).
 * 3. Daftar pertama yang punya baris satuan itu dengan `JumlahMinimum ≤ jumlah` menang (baris `JumlahMinimum`
 *    terbesar). Daftar tanpa baris yang berlaku dilewati.
 * 4. Tanpa daftar: baris dasar satuan dengan `JumlahMinimum ≤ jumlah` terbesar (`Bertingkat` bila > 1, selain itu
 *    `Dasar`); bila jumlah lebih kecil dari semua `JumlahMinimum` (misal 0,5 kg), baris dasar ber-`JumlahMinimum`
 *    terkecil (`Dasar`). Daftar harga tidak punya cadangan ini.
 * 5. Tidak ada baris dasar: null (`HargaTidakDitemukan`); satuan itu hanya untuk pembelian.
 */
final class PenentuHarga
{
    public function Tentukan(DataKatalogHarga $katalog, DataPermintaanHarga $permintaan): ?HasilHarga
    {
        if ($permintaan->jumlah->Bandingkan(Kuantitas::Nol()) <= 0) {
            throw new InvalidArgumentException('Jumlah untuk penentuan harga harus lebih dari 0.');
        }

        $barisSatuan = array_values(array_filter(
            $katalog->harga,
            fn (DataBarisProdukHarga $baris): bool => $baris->uuidProduk === $permintaan->uuidProduk
                && $baris->uuidProdukSatuan === $permintaan->uuidProdukSatuan,
        ));

        foreach ($this->UrutkanDaftarCocok($katalog->daftarHarga, $permintaan) as $daftar) {
            $baris = $this->PilihBarisBerlaku(
                array_values(array_filter($barisSatuan, fn (DataBarisProdukHarga $b): bool => $b->uuidDaftarHarga === $daftar->uuid)),
                $permintaan->jumlah,
            );

            if ($baris !== null) {
                return new HasilHarga($baris->harga, SumberHarga::DaftarHarga, $daftar->uuid, $baris->jumlahMinimum);
            }
        }

        $barisDasar = array_values(array_filter($barisSatuan, fn (DataBarisProdukHarga $b): bool => $b->uuidDaftarHarga === null));

        if ($barisDasar === []) {
            return null;
        }

        $baris = $this->PilihBarisBerlaku($barisDasar, $permintaan->jumlah);

        if ($baris === null) {
            $baris = $barisDasar[0];

            foreach ($barisDasar as $kandidat) {
                if ($kandidat->jumlahMinimum->Bandingkan($baris->jumlahMinimum) < 0) {
                    $baris = $kandidat;
                }
            }

            return new HasilHarga($baris->harga, SumberHarga::Dasar, null, $baris->jumlahMinimum);
        }

        $sumber = $baris->jumlahMinimum->Bandingkan(Kuantitas::Dari(1)) > 0 ? SumberHarga::Bertingkat : SumberHarga::Dasar;

        return new HasilHarga($baris->harga, $sumber, null, $baris->jumlahMinimum);
    }

    /**
     * @param  list<DataDaftarHargaResolusi>  $daftarHarga
     * @return list<DataDaftarHargaResolusi>
     */
    private function UrutkanDaftarCocok(array $daftarHarga, DataPermintaanHarga $permintaan): array
    {
        $cocok = array_values(array_filter($daftarHarga, fn (DataDaftarHargaResolusi $daftar): bool => $this->CekCocok($daftar, $permintaan)));

        usort($cocok, fn (DataDaftarHargaResolusi $a, DataDaftarHargaResolusi $b): int => ([$b->prioritas, $this->HitungSpesifik($b)] <=> [$a->prioritas, $this->HitungSpesifik($a)])
            ?: (strcmp($a->uuid, $b->uuid) <=> 0));

        return $cocok;
    }

    private function CekCocok(DataDaftarHargaResolusi $daftar, DataPermintaanHarga $permintaan): bool
    {
        return $daftar->aktif
            && ($daftar->uuidOutlet === null || ($permintaan->uuidOutlet !== null && in_array($permintaan->uuidOutlet, $daftar->uuidOutlet, true)))
            && ($daftar->kanal === null || $daftar->kanal === $permintaan->kanal)
            && ($daftar->tierPelanggan === null || $daftar->tierPelanggan === $permintaan->tierPelanggan)
            && ($daftar->mulaiPada === null || $daftar->mulaiPada->lessThanOrEqualTo($permintaan->waktu))
            && ($daftar->selesaiPada === null || $permintaan->waktu->lessThan($daftar->selesaiPada));
    }

    private function HitungSpesifik(DataDaftarHargaResolusi $daftar): int
    {
        return (int) ($daftar->uuidOutlet !== null)
            + (int) ($daftar->kanal !== null)
            + (int) ($daftar->tierPelanggan !== null)
            + (int) ($daftar->mulaiPada !== null || $daftar->selesaiPada !== null);
    }

    /**
     * Baris dengan `JumlahMinimum ≤ jumlah` terbesar, atau null.
     *
     * @param  list<DataBarisProdukHarga>  $baris
     */
    private function PilihBarisBerlaku(array $baris, Kuantitas $jumlah): ?DataBarisProdukHarga
    {
        $terpilih = null;

        foreach ($baris as $kandidat) {
            if ($kandidat->jumlahMinimum->Bandingkan($jumlah) > 0) {
                continue;
            }

            if ($terpilih === null || $kandidat->jumlahMinimum->Bandingkan($terpilih->jumlahMinimum) > 0) {
                $terpilih = $kandidat;
            }
        }

        return $terpilih;
    }
}
