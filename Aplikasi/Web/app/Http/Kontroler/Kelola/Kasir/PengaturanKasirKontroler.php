<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Kasir\Aksi\UbahPengaturanKasir;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Kasir\UbahPengaturanKasirPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pengaturan kasir, izin `outlet.kelola`: batas kas keluar tanpa persetujuan (BR-06.4), shift bersama
 * (BR-06.2), batas diskon manual kasir & penyetuju (BR-07.3), dan pembulatan tunai (BR-08.6).
 */
final class PengaturanKasirKontroler extends DasarKelolaKontroler
{
    public function Tampilkan(PengaturanKasirTenant $pengaturan): Response
    {
        $data = $pengaturan->Ambil();

        return Inertia::render('Kelola/Kasir/Pengaturan', [
            'BatasKasKeluar' => $data->batasKasKeluar->KeString(),
            'ShiftBersama' => $data->shiftBersama,
            'BatasDiskonManual' => (string) $data->batasDiskonManual,
            'BatasDiskonPenyetuju' => (string) $data->batasDiskonPenyetuju,
            'PembulatanTunai' => $data->AmbilPembulatanTunaiLarik(),
            'OpsiArahPembulatan' => array_map(fn (ArahPembulatan $arah): array => ['Nilai' => $arah->value, 'Label' => $arah->AmbilLabel()], ArahPembulatan::cases()),
        ]);
    }

    public function Simpan(UbahPengaturanKasirPermintaan $permintaan, UbahPengaturanKasir $ubah, PengaturanKasirTenant $pengaturan): RedirectResponse
    {
        $ubah->Jalankan($permintaan->AmbilData($pengaturan->Ambil()));

        return to_route('kelola.kasir.pengaturan')->with('Kilat', 'Pengaturan kasir disimpan.');
    }
}
