<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

/**
 * Mesin kalkulasi penjualan F-07a (PRD "Rincian F-07a", BR-07.2, BR-08.6). **Murni**: tanpa DB, model, atau facade,
 * sehingga setara dengan `Paket/MesinKasir` (Dart) dan diuji dengan test vector bersama
 * `Spesifikasi/VektorUjiKalkulasi/*.json` (CLAUDE.md #18). Uang skala 2, perhitungan antara pecahan eksak
 * (`BigRational`), pembulatan setengah ke atas (menjauhi nol) kecuali disebut lain.
 *
 * Langkah:
 * 1. `Bruto` = bulat((HargaSatuan + HargaPilihan) × Jumlah).
 * 2. Diskon baris = Σ potongan (persen dari Bruto), dibatasi Bruto; Netto = Bruto − Diskon.
 * 3. `Subtotal` = Σ Netto.
 * 4. Diskon pesanan = Σ potongan pesanan (persen dari Subtotal), dibatasi Subtotal; lalu `DiskonPoin` = nilai tukar
 *    poin (F-16b) dibatasi sisa Subtotal dan ditambahkan ke diskon pesanan; dialokasikan sebanding Netto.
 * 5. `BiayaLayanan` = bulat(persen × (Subtotal − DiskonPesanan)), dialokasikan sebanding Netto akhir.
 * 5b. `BiayaKirimNetto` = `BiayaKirim − DiskonKirim` (F-17 bagian 3), dialokasikan sebanding Netto akhir seperti biaya
 *    layanan — bukan karena kemiripan bentuk, tetapi karena pajak dihitung **per baris**: kode pajak boleh berbeda antar
 *    baris, jadi ongkir harus punya bagian per baris agar DPP-nya benar. Bila seluruh Netto akhir nol (pesanan habis
 *    didiskon) ongkirnya dibagi **rata**, sebab alokasi sebanding bobot nol mengembalikan nol semua dan Σ baris tidak
 *    lagi sama dengan dokumennya.
 * 6. Pajak per baris eksak: eksklusif `DPP = (NettoAkhir + [layanan bila SubtotalPlusLayanan] + [kirim bila
 *    kenaBiayaKirim]) × p/q`; inklusif `Dasar = NettoAkhir ÷ (1 + Σ tarif × p/q)`, `DPP = Dasar × p/q`, pajak atas biaya
 *    layanan dan ongkir selalu ditambahkan sebagai bagian eksklusif (keduanya di luar harga barang).
 * 7. Pembulatan per dokumen per kode pajak, terpisah bagian eksklusif dan inklusif; dialokasikan ke baris.
 * 8. Pembulatan tunai hanya bila ada pembayaran tunai dan sisa tunai > 0; `TotalAkhir` & `Kembalian`.
 */
final class MesinKalkulasi
{
    public function __construct(private readonly PengalokasiSisaTerbesar $pengalokasi = new PengalokasiSisaTerbesar) {}

    public function Hitung(DataKalkulasi $data): HasilKalkulasi
    {
        $jumlahBaris = count($data->baris);

        // Langkah 1–3: Bruto, diskon baris, Netto, Subtotal.
        $daftarBruto = [];
        $daftarDiskon = [];
        $daftarNetto = [];
        $subtotal = Uang::Nol();
        $diskonBaris = Uang::Nol();

        foreach ($data->baris as $indeks => $baris) {
            $bruto = $this->HitungBruto($baris);
            $diskon = $this->BatasiMaksimum($this->JumlahkanPotongan($baris->potongan, $bruto), $bruto);
            $daftarBruto[$indeks] = $bruto;
            $daftarDiskon[$indeks] = $diskon;
            $netto = $bruto->Kurangi($diskon);
            $daftarNetto[] = $netto;
            $subtotal = $subtotal->Tambah($netto);
            $diskonBaris = $diskonBaris->Tambah($diskon);
        }

        // Langkah 4: diskon pesanan.
        $diskonPesanan = $this->BatasiMaksimum($this->JumlahkanPotongan($data->potonganPesanan, $subtotal), $subtotal);
        $diskonPoin = $this->BatasiMaksimum($data->tukarPoin ?? Uang::Nol(), $subtotal->Kurangi($diskonPesanan));
        $diskonPesanan = $diskonPesanan->Tambah($diskonPoin);
        $daftarDiskonPesanan = $this->pengalokasi->AlokasikanSebanding($diskonPesanan, $daftarNetto);
        $daftarNettoAkhir = [];

        foreach ($daftarNetto as $indeks => $netto) {
            $daftarNettoAkhir[] = $netto->Kurangi($daftarDiskonPesanan[$indeks]);
        }

        // Langkah 5: biaya layanan.
        $biayaLayanan = $this->BulatkanKeSen(
            $this->UbahKeRasional($subtotal->Kurangi($diskonPesanan))->multipliedBy($data->persenBiayaLayanan)->dividedBy(100),
        );
        $daftarBiayaLayanan = $this->pengalokasi->AlokasikanSebanding($biayaLayanan, $daftarNettoAkhir);

        // Langkah 5b: ongkir netto.
        $biayaKirimNetto = $data->biayaKirim->Kurangi($data->diskonKirim);
        $daftarBiayaKirim = $this->AlokasikanBiayaKirim($biayaKirimNetto, $daftarNettoAkhir);

        // Langkah 6–7: pajak.
        $daftarPajakEksklusif = array_fill(0, $jumlahBaris, Uang::Nol());
        $daftarPajakInklusif = array_fill(0, $jumlahBaris, Uang::Nol());
        $rincianPajak = [];
        $totalPajakEksklusif = Uang::Nol();
        $totalPajakInklusif = Uang::Nol();
        $daftarKodeBaris = array_map(fn (DataBarisKalkulasi $baris): array => $this->AmbilKodePajakBaris($baris, $data), $data->baris);
        $daftarDasar = [];

        foreach ($data->baris as $indeks => $baris) {
            $daftarDasar[$indeks] = $this->HitungDasarPajakBaris(
                $daftarNettoAkhir[$indeks],
                $daftarKodeBaris[$indeks],
                $data,
                $baris->hargaTermasukPajak ?? $data->hargaTermasukPajak,
            );
        }

        foreach ($data->pajak as $pajak) {
            $tarif = BigRational::of($pajak->tarif)->dividedBy(100);
            $pengali = BigRational::ofFraction($pajak->pengaliDppPembilang, $pajak->pengaliDppPenyebut);
            $tepatEksklusif = array_fill(0, $jumlahBaris, BigRational::zero());
            $tepatInklusif = array_fill(0, $jumlahBaris, BigRational::zero());
            $dpp = BigRational::zero();

            foreach ($data->baris as $indeks => $baris) {
                if (! in_array($pajak->kode, $daftarKodeBaris[$indeks], true)) {
                    continue;
                }

                $dppLayanan = $pajak->dasarPengenaan === DasarPengenaanPajak::SubtotalPlusLayanan
                    ? $this->UbahKeRasional($daftarBiayaLayanan[$indeks])->multipliedBy($pengali)
                    : BigRational::zero();
                $dppKirim = $pajak->kenaBiayaKirim
                    ? $this->UbahKeRasional($daftarBiayaKirim[$indeks])->multipliedBy($pengali)
                    : BigRational::zero();
                $dppLuarBarang = $dppLayanan->plus($dppKirim);
                $dppBarang = $daftarDasar[$indeks]->multipliedBy($pengali);
                $dpp = $dpp->plus($dppBarang)->plus($dppLuarBarang);

                if ($baris->hargaTermasukPajak ?? $data->hargaTermasukPajak) {
                    $tepatInklusif[$indeks] = $dppBarang->multipliedBy($tarif);
                    $tepatEksklusif[$indeks] = $dppLuarBarang->multipliedBy($tarif);
                } else {
                    $tepatEksklusif[$indeks] = $dppBarang->plus($dppLuarBarang)->multipliedBy($tarif);
                }
            }

            $tepatEksklusif = array_values($tepatEksklusif);
            $tepatInklusif = array_values($tepatInklusif);
            $pajakEksklusif = $this->BulatkanKeSen($this->JumlahkanRasional($tepatEksklusif));
            $pajakInklusif = $this->BulatkanKeSen($this->JumlahkanRasional($tepatInklusif));

            foreach ($this->pengalokasi->AlokasikanTepat($pajakEksklusif, $tepatEksklusif) as $indeks => $bagian) {
                $daftarPajakEksklusif[$indeks] = $daftarPajakEksklusif[$indeks]->Tambah($bagian);
            }

            foreach ($this->pengalokasi->AlokasikanTepat($pajakInklusif, $tepatInklusif) as $indeks => $bagian) {
                $daftarPajakInklusif[$indeks] = $daftarPajakInklusif[$indeks]->Tambah($bagian);
            }

            $rincianPajak[$pajak->kode] = new HasilPajakKalkulasi($pajak->kode, $this->BulatkanKeSen($dpp), $pajakEksklusif->Tambah($pajakInklusif));
            $totalPajakEksklusif = $totalPajakEksklusif->Tambah($pajakEksklusif);
            $totalPajakInklusif = $totalPajakInklusif->Tambah($pajakInklusif);
        }

        // Langkah 8: pembulatan tunai, total akhir, kembalian.
        $totalSebelumPembulatan = $subtotal->Kurangi($diskonPesanan)->Tambah($biayaLayanan)->Tambah($biayaKirimNetto)->Tambah($totalPajakEksklusif);
        $nonTunai = Uang::Nol();
        $adaTunai = false;

        foreach ($data->pembayaran as $pembayaran) {
            if ($pembayaran->tunai) {
                $adaTunai = true;
            } elseif ($pembayaran->jumlah !== null) {
                $nonTunai = $nonTunai->Tambah($pembayaran->jumlah);
            }
        }

        $pembulatan = $adaTunai ? $this->HitungPembulatanTunai($totalSebelumPembulatan->Kurangi($nonTunai), $data->pembulatanTunai) : Uang::Nol();
        $totalAkhir = $totalSebelumPembulatan->Tambah($pembulatan);

        $hasilBaris = [];

        foreach ($data->baris as $indeks => $baris) {
            $hasilBaris[] = new HasilBarisKalkulasi(
                $daftarBruto[$indeks],
                $daftarDiskon[$indeks],
                $daftarDiskonPesanan[$indeks],
                $daftarBiayaLayanan[$indeks],
                $daftarPajakEksklusif[$indeks]->Tambah($daftarPajakInklusif[$indeks]),
                $daftarPajakEksklusif[$indeks],
                $daftarNettoAkhir[$indeks]->Tambah($daftarBiayaLayanan[$indeks])->Tambah($daftarBiayaKirim[$indeks])->Tambah($daftarPajakEksklusif[$indeks]),
                $daftarBiayaKirim[$indeks],
            );
        }

        return new HasilKalkulasi(
            $subtotal,
            $diskonBaris,
            $diskonPesanan,
            $diskonBaris->Tambah($diskonPesanan),
            $biayaLayanan,
            $totalPajakEksklusif->Tambah($totalPajakInklusif),
            $totalPajakEksklusif,
            $pembulatan,
            $totalAkhir,
            $adaTunai ? $this->HitungKembalian($data->pembayaran, $totalAkhir->Kurangi($nonTunai)) : null,
            $rincianPajak,
            $hasilBaris,
            $diskonPoin,
            $data->biayaKirim,
            $data->diskonKirim,
        );
    }

    private function HitungBruto(DataBarisKalkulasi $baris): Uang
    {
        $harga = $baris->hargaSatuan->Tambah($baris->hargaPilihan ?? Uang::Nol());

        return $this->BulatkanKeSen($this->UbahKeRasional($harga)->multipliedBy($baris->jumlah->KeDesimal()));
    }

    /**
     * @param  list<DataPotongan>  $daftarPotongan
     */
    private function JumlahkanPotongan(array $daftarPotongan, Uang $dasar): Uang
    {
        $total = Uang::Nol();

        foreach ($daftarPotongan as $potongan) {
            $total = $total->Tambah($potongan->persen !== null
                ? $this->BulatkanKeSen($this->UbahKeRasional($dasar)->multipliedBy($potongan->persen)->dividedBy(100))
                : $potongan->jumlah ?? Uang::Nol());
        }

        return $total;
    }

    /**
     * Kode pajak yang berlaku untuk baris: null = semua pajak dokumen; urutan mengikuti pajak dokumen, tanpa duplikat.
     *
     * @return list<string>
     */
    private function AmbilKodePajakBaris(DataBarisKalkulasi $baris, DataKalkulasi $data): array
    {
        $semuaKode = array_map(fn (DataPajakKalkulasi $pajak): string => $pajak->kode, $data->pajak);

        return $baris->kodePajak === null
            ? $semuaKode
            : array_values(array_filter($semuaKode, fn (string $kode): bool => in_array($kode, $baris->kodePajak, true)));
    }

    /**
     * Dasar barang sebelum pengali DPP: Netto akhir (eksklusif) atau Netto akhir ÷ (1 + Σ tarif × p/q) (inklusif).
     *
     * @param  list<string>  $kodeBaris
     */
    /**
     * Alokasi ongkir netto ke baris, sebanding Netto akhir.
     *
     * Bila **seluruh** Netto akhir nol — pesanan yang habis didiskon, atau seluruh barisnya gratis — alokasi sebanding
     * mengembalikan nol semua (`PengalokasiSisaTerbesar`: "bobot nol semua = semua nol"). Itu benar untuk diskon dan
     * biaya layanan, yang ikut nol saat dasarnya nol, tetapi **salah untuk ongkir**: ongkirnya nominal dan tetap
     * ditagih, sehingga Σ baris tidak lagi sama dengan dokumennya dan DPP-nya hilang. Dalam keadaan itu ongkir dibagi
     * **rata** antar baris.
     *
     * @param  list<Uang>  $nettoAkhir
     * @return list<Uang>
     */
    private function AlokasikanBiayaKirim(Uang $biayaKirimNetto, array $nettoAkhir): array
    {
        if ($nettoAkhir === []) {
            return [];
        }

        $adaBobot = false;

        foreach ($nettoAkhir as $satu) {
            if (! $satu->BernilaiNol()) {
                $adaBobot = true;

                break;
            }
        }

        $bobot = $adaBobot ? $nettoAkhir : array_fill(0, count($nettoAkhir), Uang::Dari('1'));

        return $this->pengalokasi->AlokasikanSebanding($biayaKirimNetto, $bobot);
    }

    private function HitungDasarPajakBaris(Uang $nettoAkhir, array $kodeBaris, DataKalkulasi $data, bool $termasukPajak): BigRational
    {
        $netto = $this->UbahKeRasional($nettoAkhir);

        if (! $termasukPajak) {
            return $netto;
        }

        $faktor = BigRational::one();

        foreach ($data->pajak as $pajak) {
            if (in_array($pajak->kode, $kodeBaris, true)) {
                $faktor = $faktor->plus(
                    BigRational::of($pajak->tarif)->dividedBy(100)->multipliedBy(BigRational::ofFraction($pajak->pengaliDppPembilang, $pajak->pengaliDppPenyebut)),
                );
            }
        }

        return $netto->dividedBy($faktor);
    }

    private function HitungPembulatanTunai(Uang $sisaTunai, ?DataPembulatanTunai $pengaturan): Uang
    {
        if ($pengaturan === null || $sisaTunai->Bandingkan(Uang::Nol()) <= 0) {
            return Uang::Nol();
        }

        return $sisaTunai
            ->BulatkanKeKelipatan($pengaturan->kelipatan, $pengaturan->arah->AmbilModePembulatan())
            ->Kurangi($sisaTunai);
    }

    /**
     * Kembalian = uang tunai diterima − bagian tunai tagihan. Ada tunai tanpa jumlah (uang pas) = 0.
     *
     * @param  list<DataPembayaranKalkulasi>  $daftarPembayaran
     */
    private function HitungKembalian(array $daftarPembayaran, Uang $tagihanTunai): Uang
    {
        $diterima = Uang::Nol();

        foreach ($daftarPembayaran as $pembayaran) {
            if (! $pembayaran->tunai) {
                continue;
            }

            if ($pembayaran->jumlah === null) {
                return Uang::Nol();
            }

            $diterima = $diterima->Tambah($pembayaran->jumlah);
        }

        return $diterima->Kurangi($tagihanTunai);
    }

    private function BatasiMaksimum(Uang $nilai, Uang $maksimum): Uang
    {
        return $nilai->Bandingkan($maksimum) > 0 ? $maksimum : $nilai;
    }

    /**
     * @param  list<BigRational>  $daftar
     */
    private function JumlahkanRasional(array $daftar): BigRational
    {
        $total = BigRational::zero();

        foreach ($daftar as $nilai) {
            $total = $total->plus($nilai);
        }

        return $total;
    }

    private function UbahKeRasional(Uang $uang): BigRational
    {
        return BigRational::of($uang->KeString());
    }

    private function BulatkanKeSen(BigRational $nilai): Uang
    {
        return Uang::Dari($nilai->toScale(Uang::SKALA, RoundingMode::HalfUp));
    }
}
