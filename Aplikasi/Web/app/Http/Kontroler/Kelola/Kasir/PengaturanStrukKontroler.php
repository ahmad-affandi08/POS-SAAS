<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Kasir;

use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Tenant\Aksi\UbahPengaturanStruk;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Kasir\UbahPengaturanStrukPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pengaturan struk (PLT-06, PRD v1.79), izin `outlet.kelola`: satu pengaturan untuk semua outlet dengan
 * pratinjau struk thermal. Data profil (nama usaha, NPWP, logo) hanya untuk pratinjau; diubah di profil usaha.
 */
final class PengaturanStrukKontroler extends DasarKelolaKontroler
{
    public function Tampilkan(PengaturanStrukTenant $pengaturan, ProfilTenant $profilTenant, PemeriksaFiturTenant $fitur, OutletUtama $outletUtama): Response
    {
        $profil = $profilTenant->Ambil($this->IdTenant());

        return Inertia::render('Kelola/Kasir/Struk', [
            'Pengaturan' => $pengaturan->Ambil()->KeLarik(),
            'Profil' => [
                'NamaUsaha' => $profil['Nama'],
                'Npwp' => $profil['Npwp'],
                'NamaOutlet' => $outletUtama->CariAktifRingkas($outletUtama->AmbilId())?->nama,
                'TautanLogo' => $profil['PathLogo'] === null ? null : route('kelola.panduan-awal.profil-usaha.logo', ['v' => substr(hash('sha256', $profil['PathLogo']), 0, 12)]),
                'TandaAir' => ! $fitur->CekAktif($this->IdTenant(), 'struk.tanpa-watermark'),
            ],
        ]);
    }

    public function Simpan(UbahPengaturanStrukPermintaan $permintaan, UbahPengaturanStruk $ubah): RedirectResponse
    {
        $ubah->Jalankan($permintaan->AmbilData());

        return to_route('kelola.kasir.struk')->with('Kilat', 'Pengaturan struk disimpan. Kasir memakainya setelah data diperbarui.');
    }
}
