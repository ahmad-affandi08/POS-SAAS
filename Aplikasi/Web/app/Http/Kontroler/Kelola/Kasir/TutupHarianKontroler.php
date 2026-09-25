<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Kasir\Aksi\TutupHarianOutlet;
use App\Domain\Kasir\Kueri\PemeriksaanTutupHarian;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Kasir\TutupHarianPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Tutup harian (F-15 End of Day): 14 tanggal bisnis terakhir per outlet yang boleh diakses (izin
 * `laporan.penjualan.lihat`); menutup hari butuh `akuntansi.kelola` ("tutup buku").
 */
final class TutupHarianKontroler extends DasarKelolaKontroler
{
    public function Tampilkan(PemeriksaanTutupHarian $pemeriksaan, AksesPengguna $akses): Response
    {
        return Inertia::render('Kelola/Kasir/TutupHarian', [
            'Hari' => $pemeriksaan->Daftar($this->IdTenant(), $this->IdOutletBoleh()),
            'Izin' => ['Kelola' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::AkuntansiKelola)],
        ]);
    }

    public function Tutup(TutupHarianPermintaan $permintaan, TutupHarianOutlet $tutup): RedirectResponse
    {
        $outlet = $this->CariOutlet($permintaan->string('Outlet')->toString());
        $tanggal = $permintaan->AmbilTanggal();
        $tutup->Jalankan($outlet->Id, $tanggal, $this->Pelaku()->Id, $permintaan->boolean('AbaikanPeringatan'));

        return to_route('kelola.kasir.tutup-harian')->with('Kilat', "Hari {$tanggal->translatedFormat('j F Y')} di {$outlet->Nama} ditutup.");
    }
}
