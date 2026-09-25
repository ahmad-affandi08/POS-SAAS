<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\BukaKunciPeriode;
use App\Domain\Akuntansi\Aksi\KunciPeriodeAkuntansi;
use App\Domain\Akuntansi\Kueri\DaftarPeriodeAkuntansi;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Permintaan\Kelola\Akuntansi\BukaKunciPeriodePermintaan;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Tutup buku (F-15): lihat periode (izin `laporan.keuangan.lihat`), kunci & buka kunci periode (izin
 * `akuntansi.kelola`, buka kunci wajib alasan).
 */
final class TutupBukuKontroler extends DasarAkuntansiKontroler
{
    public function Tampilkan(DaftarPeriodeAkuntansi $daftar, ProfilTenant $profil): Response
    {
        $bulanIni = CarbonImmutable::now($profil->Ambil($this->IdTenant())['ZonaWaktu'])->startOfMonth();

        return Inertia::render('Kelola/Akuntansi/TutupBuku', [
            'Periode' => $daftar->Ambil($this->IdTenant(), $bulanIni),
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
}
