<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Aksi\UbahPengaturanKasir;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Kasir\UbahPengaturanKasirPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pengaturan kasir (F-06), izin `outlet.kelola`: batas kas keluar tanpa persetujuan (BR-06.4) dan shift
 * bersama (BR-06.2).
 */
final class PengaturanKasirKontroler extends DasarKelolaKontroler
{
    public function Tampilkan(PengaturanKasirTenant $pengaturan): Response
    {
        $data = $pengaturan->Ambil();

        return Inertia::render('Kelola/Kasir/Pengaturan', [
            'BatasKasKeluar' => $data->batasKasKeluar->KeString(),
            'ShiftBersama' => $data->shiftBersama,
        ]);
    }

    public function Simpan(UbahPengaturanKasirPermintaan $permintaan, UbahPengaturanKasir $ubah): RedirectResponse
    {
        $ubah->Jalankan(Uang::Dari((string) $permintaan->validated('BatasKasKeluar')), $permintaan->boolean('ShiftBersama'));

        return to_route('kelola.kasir.pengaturan')->with('Kilat', 'Pengaturan kasir disimpan.');
    }
}
