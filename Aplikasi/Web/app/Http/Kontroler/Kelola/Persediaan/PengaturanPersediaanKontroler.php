<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Persediaan\Aksi\UbahPengaturanPersediaan;
use App\Domain\Persediaan\Kueri\PengaturanPersediaan;
use App\Http\Permintaan\Kelola\Persediaan\UbahPengaturanPersediaanPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pengaturan persediaan, izin `akuntansi.kelola` (DesainF05a D): metode HPP dan izin stok minus tenant.
 */
final class PengaturanPersediaanKontroler extends DasarPersediaanKontroler
{
    public function Tampilkan(PengaturanPersediaan $pengaturan): Response
    {
        return Inertia::render('Kelola/Persediaan/Pengaturan', $pengaturan->Ambil());
    }

    public function Simpan(UbahPengaturanPersediaanPermintaan $permintaan, UbahPengaturanPersediaan $ubah): RedirectResponse
    {
        $ubah->Jalankan($permintaan->AmbilMetodeHpp(), $permintaan->AmbilStokBolehMinus());

        return to_route('kelola.persediaan.pengaturan')->with('Kilat', 'Pengaturan persediaan disimpan.');
    }
}
