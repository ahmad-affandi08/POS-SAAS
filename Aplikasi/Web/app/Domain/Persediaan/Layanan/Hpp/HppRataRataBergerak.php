<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use Brick\Math\BigDecimal;
use LogicException;

/**
 * Strategi HPP rata-rata bergerak bernilai jangkar N/Q (DesainF05a C.3, BR-04.2/04.3).
 *
 * Masuk (q > 0), V = nilai (`Ditentukan`) atau Nilai(q, A ?? 0) (`Berjalan`):
 * - Q ≥ 0: Q′ = Q+q; N′ = N+V; A′ = Hpp(N′, Q′); TotalHpp = V.
 * - Q < 0 (BR-04.3): c′ = hppSatuan ?? Hpp(V, q); N′ = Q′=0 ? 0 : Nilai(Q′, c′)·sign(Q′); TotalHpp = N′ − N;
 *   A′ = c′. Selisih = TotalHpp − V.
 *
 * Keluar (q < 0, d = |q|), nilai berjalan dengan A ?? 0:
 * - Q > 0, Q′ > 0: −min(Nilai(d, A), max(N, 0)); A tetap.  Q′ = 0: −N.  Q′ < 0: −(N + Nilai(Q′, A)).
 * - Q ≤ 0: −Nilai(d, A).
 * - `Ditentukan` D: Q′ = 0 → −N; Q′ > 0 → −min(D, max(N, 0)) dan A′ = Hpp(N′, Q′); Q′ < 0 → seperti berjalan.
 *   NilaiDiminta = −D, Selisih = TotalHpp + D.
 *
 * A = null berarti HPP belum diketahui: dinilai 0 dan `hppTidakDiketahui = true`.
 */
final class HppRataRataBergerak implements StrategiHpp
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
            $tidakDiketahui = $keadaan->hppRataRata === null;
            $hppBaris = $keadaan->hppRataRata ?? BigDecimal::zero();
            $nilai = AritmetikaHpp::Nilai($q, $hppBaris);
        }

        $jumlahBaru = $keadaan->jumlah->Tambah($q);

        if (! $keadaan->jumlah->BernilaiNegatif()) {
            $nilaiBaru = $keadaan->nilai->Tambah($nilai);
            $keadaan->hppRataRata = AritmetikaHpp::Hpp($nilaiBaru, $jumlahBaru);
        } else {
            // BR-04.3: menerima barang saat stok minus → saldo baru dinilai ulang pada HPP penerimaan ini.
            $nilaiBaru = AritmetikaHpp::CekNol($jumlahBaru) ? Uang::Nol() : AritmetikaHpp::NilaiBertanda($jumlahBaru, $hppBaris);
            $keadaan->hppRataRata = $hppBaris;
        }

        $totalHpp = $nilaiBaru->Kurangi($keadaan->nilai);
        $keadaan->jumlah = $jumlahBaru;
        $keadaan->nilai = $nilaiBaru;

        return new HasilHpp($hppBaris, $totalHpp, $nilai, $tidakDiketahui);
    }

    private function TerapkanKeluar(KeadaanHpp $keadaan, MasukanHpp $masukan): HasilHpp
    {
        $q = $masukan->jumlah;
        $d = $q->Negasi();
        $jumlahBaru = $keadaan->jumlah->Tambah($q);
        $biaya = $keadaan->hppRataRata ?? BigDecimal::zero();
        $tidakDiketahui = $keadaan->hppRataRata === null;
        $hppBaris = $biaya;

        if ($masukan->mode === ModeNilaiMutasi::Ditentukan) {
            $diminta = $masukan->nilai ?? throw new LogicException('Mode Ditentukan wajib punya nilai.');

            if (AritmetikaHpp::CekNol($jumlahBaru)) {
                $totalHpp = AritmetikaHpp::Negasi($keadaan->nilai);
                $tidakDiketahui = false;
            } elseif (AritmetikaHpp::CekPositif($jumlahBaru)) {
                $totalHpp = AritmetikaHpp::Negasi(AritmetikaHpp::AmbilMinimum($diminta, AritmetikaHpp::AmbilMaksimum($keadaan->nilai, Uang::Nol())));
                $tidakDiketahui = false;
            } else {
                $totalHpp = $this->HitungNilaiBerjalan($keadaan, $d, $jumlahBaru, $biaya);
            }

            $nilaiDiminta = AritmetikaHpp::Negasi($diminta);
            $hppBaris = $masukan->hppSatuan ?? ($totalHpp->BernilaiNol() ? $biaya : AritmetikaHpp::Hpp(AritmetikaHpp::AmbilMutlak($totalHpp), $d));
        } else {
            $totalHpp = $this->HitungNilaiBerjalan($keadaan, $d, $jumlahBaru, $biaya);
            $nilaiDiminta = $totalHpp;

            if (AritmetikaHpp::CekNol($jumlahBaru) && $keadaan->jumlah->KeDesimal()->isPositive()) {
                $tidakDiketahui = false;
            }
        }

        $nilaiBaru = $keadaan->nilai->Tambah($totalHpp);

        if ($masukan->mode === ModeNilaiMutasi::Ditentukan && AritmetikaHpp::CekPositif($jumlahBaru)) {
            $keadaan->hppRataRata = AritmetikaHpp::Hpp($nilaiBaru, $jumlahBaru);
        }

        $keadaan->jumlah = $jumlahBaru;
        $keadaan->nilai = $nilaiBaru;

        return new HasilHpp($hppBaris, $totalHpp, $nilaiDiminta, $tidakDiketahui);
    }

    /** Nilai keluar berjalan (bertanda negatif) untuk d satuan dari Q menjadi Q′ dengan biaya A. */
    private function HitungNilaiBerjalan(KeadaanHpp $keadaan, Kuantitas $d, Kuantitas $jumlahBaru, BigDecimal $biaya): Uang
    {
        if (! AritmetikaHpp::CekPositif($keadaan->jumlah)) {
            return AritmetikaHpp::Negasi(AritmetikaHpp::Nilai($d, $biaya));
        }

        if (AritmetikaHpp::CekPositif($jumlahBaru)) {
            return AritmetikaHpp::Negasi(AritmetikaHpp::AmbilMinimum(
                AritmetikaHpp::Nilai($d, $biaya),
                AritmetikaHpp::AmbilMaksimum($keadaan->nilai, Uang::Nol()),
            ));
        }

        if (AritmetikaHpp::CekNol($jumlahBaru)) {
            return AritmetikaHpp::Negasi($keadaan->nilai);
        }

        // Melewati nol: seluruh nilai tersisa keluar, ditambah bagian minus dinilai pada A.
        return AritmetikaHpp::Negasi($keadaan->nilai->Tambah(AritmetikaHpp::Nilai($jumlahBaru, $biaya)));
    }
}
