<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\HapusPemetaanAkunOutlet;
use App\Domain\Akuntansi\Aksi\UbahPemetaanAkun;
use App\Domain\Akuntansi\Kueri\DaftarPemetaanAkun;
use App\Http\Permintaan\Kelola\Akuntansi\UbahPemetaanAkunPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemetaan akun (F-13a): lihat `laporan.keuangan.lihat`, ubah `akuntansi.kelola`. Override outlet hanya untuk outlet
 * dalam akses pelaku (di luar akses = 404). Perubahan berlaku untuk jurnal berikutnya.
 */
final class PemetaanAkunKontroler extends DasarAkuntansiKontroler
{
    public function Daftar(DaftarPemetaanAkun $daftar): Response
    {
        return Inertia::render('Kelola/Akuntansi/Pemetaan/Daftar', [...$daftar->Ambil($this->IdOutletBoleh()), 'Izin' => [
            'Kelola' => $this->CekIzinKelola(),
            // Pemetaan umum berlaku untuk semua outlet: hanya pelaku tanpa batas outlet yang boleh mengubahnya.
            'UbahSemuaOutlet' => $this->IdOutletBoleh() === null,
        ]]);
    }

    public function Simpan(UbahPemetaanAkunPermintaan $permintaan, UbahPemetaanAkun $ubah): RedirectResponse
    {
        $uuidOutlet = $permintaan->AmbilUuidOutlet();
        // Pemetaan tingkat tenant berlaku untuk semua outlet: hanya pelaku tanpa batas outlet yang boleh mengubahnya.
        abort_if($uuidOutlet === null && $this->IdOutletBoleh() !== null, 403);
        $peran = $permintaan->AmbilPeran();
        $ubah->Jalankan($peran, $uuidOutlet === null ? null : $this->CariOutlet($uuidOutlet)->Id, (string) $permintaan->validated('UuidAkun'));

        return to_route('kelola.akuntansi.pemetaan.daftar')->with('Kilat', "Pemetaan {$peran->AmbilLabel()} disimpan. Berlaku untuk jurnal berikutnya.");
    }

    public function Hapus(UbahPemetaanAkunPermintaan $permintaan, HapusPemetaanAkunOutlet $hapus): RedirectResponse
    {
        $peran = $permintaan->AmbilPeran();
        $outlet = $this->CariOutlet((string) $permintaan->AmbilUuidOutlet());
        $hapus->Jalankan($peran, $outlet->Id);

        return to_route('kelola.akuntansi.pemetaan.daftar')->with('Kilat', "Pemetaan khusus {$outlet->Nama} untuk {$peran->AmbilLabel()} dihapus; outlet ini kembali memakai pemetaan umum.");
    }
}
