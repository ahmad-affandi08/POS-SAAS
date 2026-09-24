<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use Brick\Math\BigDecimal;
use LogicException;

/**
 * Strategi HPP FIFO berbasis lapisan (DesainF05a C.3).
 *
 * - Masuk membuat lapisan (q, V, hppSatuan ?? Hpp(V, q)); `Berjalan` dinilai pada HPP lapisan terbaru, lalu A,
 *   lalu 0. Masuk saat Q < 0 memakai aturan target yang sama dengan rata-rata bergerak (BR-04.3); lapisan
 *   (Q′, Nilai(Q′, c′)) hanya dibuat bila Q′ > 0.
 * - Keluar mengonsumsi lapisan terbuka tertua dulu (urut Id; produk batch hanya lapisan batch itu). Sisa kebutuhan
 *   (stok minus) dinilai pada HPP lapisan terbaru, lalu A, lalu 0.
 * - Keluar `Ditentukan` ber-`idMutasiAsal` mengonsumsi tepat lapisan ber-IdMutasiSumber itu; sisa lapisan kurang →
 *   `LapisanSudahTerpakai`. Keluar `Ditentukan` lain dinilai seperti berjalan; NilaiDiminta = −D.
 * - HppSatuan baris keluar = Hpp(|TotalHpp|, q). A′ = Hpp(N′, Q′) bila Q′ > 0, selain itu A tetap (masuk saat
 *   minus: A′ = c′).
 *
 * Invarian: saat Q ≥ 0, Σ JumlahSisa lapisan terbuka = Q dan Σ NilaiSisa = N; saat Q ≤ 0 tidak ada lapisan terbuka.
 */
final class HppFifo implements StrategiHpp
{
    public function Terapkan(KeadaanHpp $keadaan, MasukanHpp $masukan): HasilHpp
    {
        if (AritmetikaHpp::CekNol($masukan->jumlah)) {
            throw new LogicException('Jumlah mutasi tidak boleh 0.');
        }

        return $masukan->jumlah->BernilaiNegatif()
            ? $this->TerapkanKeluar($keadaan, $masukan)
            : $this->TerapkanMasuk($keadaan, $masukan);
    }

    private function TerapkanMasuk(KeadaanHpp $keadaan, MasukanHpp $masukan): HasilHpp
    {
        $q = $masukan->jumlah;
        $tidakDiketahui = false;

        if ($masukan->mode === ModeNilaiMutasi::Ditentukan) {
            $nilai = $masukan->nilai ?? throw new LogicException('Mode Ditentukan wajib punya nilai.');
            $hppBaris = $masukan->hppSatuan ?? AritmetikaHpp::Hpp($nilai, $q);
        } else {
            $biaya = $keadaan->hppLapisanTerakhir ?? $keadaan->hppRataRata;
            $tidakDiketahui = $biaya === null;
            $hppBaris = $biaya ?? BigDecimal::zero();
            $nilai = AritmetikaHpp::Nilai($q, $hppBaris);
        }

        $jumlahBaru = $keadaan->jumlah->Tambah($q);
        $lapisanBaru = null;

        if (! $keadaan->jumlah->BernilaiNegatif()) {
            $nilaiBaru = $keadaan->nilai->Tambah($nilai);
            $lapisanBaru = $this->BuatLapisan($masukan, $q, $nilai, $hppBaris);
        } else {
            // BR-04.3: saldo minus → saldo baru dinilai ulang pada HPP penerimaan ini; lapisan hanya untuk sisa positif.
            $nilaiBaru = AritmetikaHpp::CekNol($jumlahBaru) ? Uang::Nol() : AritmetikaHpp::NilaiBertanda($jumlahBaru, $hppBaris);

            if (AritmetikaHpp::CekPositif($jumlahBaru)) {
                $lapisanBaru = $this->BuatLapisan($masukan, $jumlahBaru, $nilaiBaru, $hppBaris);
            }
        }

        if ($lapisanBaru !== null) {
            $keadaan->lapisan[] = $lapisanBaru;
            $keadaan->hppLapisanTerakhir = $hppBaris;
        }

        $totalHpp = $nilaiBaru->Kurangi($keadaan->nilai);
        $keadaan->hppRataRata = AritmetikaHpp::CekPositif($jumlahBaru) ? AritmetikaHpp::Hpp($nilaiBaru, $jumlahBaru) : $hppBaris;
        $keadaan->jumlah = $jumlahBaru;
        $keadaan->nilai = $nilaiBaru;

        return new HasilHpp($hppBaris, $totalHpp, $nilai, $tidakDiketahui, $lapisanBaru);
    }

    private function TerapkanKeluar(KeadaanHpp $keadaan, MasukanHpp $masukan): HasilHpp
    {
        $q = $masukan->jumlah;
        $d = $q->Negasi();
        $jumlahBaru = $keadaan->jumlah->Tambah($q);
        $tidakDiketahui = false;

        if ($masukan->mode === ModeNilaiMutasi::Ditentukan && $masukan->idMutasiAsal !== null) {
            $diambil = $this->KonsumsiLapisanAsal($keadaan, $masukan->idMutasiAsal, $d);
        } else {
            $diambil = Uang::Nol();
            $kebutuhan = $d;

            foreach ($keadaan->AmbilLapisanTerbuka($masukan->idBatchStok) as $lapisan) {
                if (AritmetikaHpp::CekNol($kebutuhan)) {
                    break;
                }

                $ambil = AritmetikaHpp::AmbilMinimumJumlah($lapisan->jumlahSisa, $kebutuhan);
                $diambil = $diambil->Tambah($lapisan->Konsumsi($ambil));
                $kebutuhan = $kebutuhan->Kurangi($ambil);
            }

            if (! AritmetikaHpp::CekNol($kebutuhan)) {
                // Stok minus: sisa kebutuhan dinilai pada HPP lapisan terbaru, lalu A, lalu 0.
                $biaya = $keadaan->hppLapisanTerakhir ?? $keadaan->hppRataRata;
                $tidakDiketahui = $biaya === null;
                $diambil = $diambil->Tambah(AritmetikaHpp::Nilai($kebutuhan, $biaya ?? BigDecimal::zero()));
            }
        }

        $totalHpp = AritmetikaHpp::Negasi($diambil);

        if ($masukan->mode === ModeNilaiMutasi::Ditentukan) {
            $nilaiDiminta = AritmetikaHpp::Negasi($masukan->nilai ?? throw new LogicException('Mode Ditentukan wajib punya nilai.'));
        } else {
            $nilaiDiminta = $totalHpp;
        }

        $nilaiBaru = $keadaan->nilai->Tambah($totalHpp);

        if (AritmetikaHpp::CekPositif($jumlahBaru)) {
            $keadaan->hppRataRata = AritmetikaHpp::Hpp($nilaiBaru, $jumlahBaru);
        }

        $keadaan->jumlah = $jumlahBaru;
        $keadaan->nilai = $nilaiBaru;

        return new HasilHpp(AritmetikaHpp::Hpp($diambil, $d), $totalHpp, $nilaiDiminta, $tidakDiketahui);
    }

    private function KonsumsiLapisanAsal(KeadaanHpp $keadaan, int $idMutasiAsal, Kuantitas $d): Uang
    {
        foreach ($keadaan->AmbilLapisanTerbuka() as $lapisan) {
            if ($lapisan->idMutasiSumber === $idMutasiAsal && $lapisan->jumlahSisa->Bandingkan($d) >= 0) {
                return $lapisan->Konsumsi($d);
            }
        }

        throw new PelanggaranAturanBisnis(
            'LapisanSudahTerpakai',
            'Stok dari mutasi asal sudah terpakai sebagian atau seluruhnya, sehingga tidak bisa dibalik. Koreksi lewat penyesuaian stok.',
            'Baris',
            detail: ['IdMutasiAsal' => $idMutasiAsal],
        );
    }

    private function BuatLapisan(MasukanHpp $masukan, Kuantitas $jumlah, Uang $nilai, BigDecimal $hppSatuan): LapisanHpp
    {
        $lapisan = new LapisanHpp(null, null, $masukan->kunciBaris, $masukan->idBatchStok, $jumlah, $jumlah, $hppSatuan, $nilai, $nilai);
        $lapisan->berubah = true;

        return $lapisan;
    }
}
