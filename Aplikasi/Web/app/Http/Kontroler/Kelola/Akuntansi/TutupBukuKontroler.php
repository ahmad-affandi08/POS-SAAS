<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\BukaKunciPeriode;
use App\Domain\Akuntansi\Aksi\KunciPeriodeAkuntansi;
use App\Domain\Akuntansi\Aksi\TutupTahunAkuntansi;
use App\Domain\Akuntansi\Kueri\DaftarPeriodeAkuntansi;
use App\Domain\Akuntansi\Kueri\StatusTutupTahun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Permintaan\Kelola\Akuntansi\BukaKunciPeriodePermintaan;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Tutup buku (F-15): lihat periode & tahun buku (izin `laporan.keuangan.lihat`), kunci & buka kunci periode,
 * tutup tahun J-15.1 (izin `akuntansi.kelola`, buka kunci wajib alasan).
 */
final class TutupBukuKontroler extends DasarAkuntansiKontroler
{
    public function Tampilkan(DaftarPeriodeAkuntansi $daftar, StatusTutupTahun $tahun, ProfilTenant $profil): Response
    {
        $bulanIni = CarbonImmutable::now($profil->Ambil($this->IdTenant())['ZonaWaktu'])->startOfMonth();

        return Inertia::render('Kelola/Akuntansi/TutupBuku', [
            'Periode' => $daftar->Ambil($this->IdTenant(), $bulanIni),
            'Tahun' => $tahun->Daftar($this->IdTenant(), $bulanIni->year),
            'Izin' => ['Kelola' => $this->CekIzinKelola()],
        ]);
    }

    public function Kunci(string $periode, KunciPeriodeAkuntansi $kunci): RedirectResponse
    {
        $kunci->Jalankan($periode, $this->Pelaku()->Id);

        return to_route('kelola.akuntansi.tutup-buku')->with('Kilat', 'Periode '.PenjagaKunciPeriode::FormatPeriode($periode).' dikunci.');
    }

    public function BukaKunci(string $periode, BukaKunciPeriodePermintaan $permintaan, BukaKunciPeriode $buka): RedirectResponse
    {
        $buka->Jalankan($periode, $permintaan->string('Alasan')->toString());

        return to_route('kelola.akuntansi.tutup-buku')->with('Kilat', 'Kunci periode '.PenjagaKunciPeriode::FormatPeriode($periode).' dibuka.');
    }

    public function TutupTahun(int $tahun, TutupTahunAkuntansi $tutup): RedirectResponse
    {
        $hasil = $tutup->Jalankan($tahun, $this->Pelaku()->Id);

        return to_route('kelola.akuntansi.tutup-buku')->with('Kilat', "Tahun buku {$tahun} ditutup dengan jurnal {$hasil->nomor}.");
    }
}
