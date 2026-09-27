<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Aksi\SimpanProfilUsaha;
use App\Domain\PanduanAwal\Kueri\ProgresPanduan;
use App\Domain\Referensi\Kueri\WilayahKota;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Http\Permintaan\Kelola\PanduanAwal\SimpanProfilUsahaPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman Pengaturan tenant (F-01): satu tempat untuk menemukan semua pengaturan, plus profil usaha (F-01 langkah 1)
 * yang sebelumnya hanya bisa diubah dari dalam wizard panduan awal sehingga praktis tidak bisa ditemukan lagi setelah
 * panduan selesai.
 *
 * Isi daftar tautannya ada di frontend (`Pustaka/DaftarPengaturan`) karena penyaringan izin & gembok fitur paket
 * memakai props bersama yang sama dengan menu samping; halaman indeks tidak perlu payload sendiri.
 *
 * Profil usaha memakai Aksi, Kueri, dan Permintaan yang sama dengan langkah panduan awal, jadi aturan validasi dan
 * efeknya (termasuk zona waktu mengikuti kota) tidak bisa bercabang.
 */
final class PengaturanKontroler extends DasarKelolaKontroler
{
    public function Indeks(): Response
    {
        return Inertia::render('Kelola/Pengaturan/Indeks');
    }

    public function TampilkanProfilUsaha(ProfilTenant $profilTenant, WilayahKota $wilayahKota): Response
    {
        $outlet = $this->OutletProfil();
        $profil = $profilTenant->Ambil($this->IdTenant());

        return Inertia::render('Kelola/Pengaturan/ProfilUsaha', [
            'Profil' => [
                'NamaUsaha' => $profil['Nama'],
                'Alamat' => $outlet->Alamat,
                'KodeKota' => $outlet->KodeKota,
                'Npwp' => $profil['Npwp'],
                'Pkp' => $profil['Pkp'],
                'TautanLogo' => $profil['PathLogo'] === null
                    ? null
                    : route('kelola.pengaturan.profil-usaha.logo', ['v' => substr(hash('sha256', $profil['PathLogo']), 0, 12)]),
            ],
            'Kota' => array_map(fn (array $kota): array => [
                'Kode' => $kota['Kode'],
                'Nama' => $kota['Nama'],
                'NamaProvinsi' => $kota['NamaProvinsi'],
                'ZonaWaktu' => $kota['ZonaWaktu'],
            ], $wilayahKota->AmbilSemua()),
            'BatasLogo' => ['UkuranMaksimalKb' => (int) config('tenant.UkuranMaksimalLogoKb'), 'Ekstensi' => array_values((array) config('tenant.EkstensiLogo'))],
        ]);
    }

    public function SimpanProfilUsaha(SimpanProfilUsahaPermintaan $permintaan, SimpanProfilUsaha $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->OutletProfil(), $permintaan->AmbilData(), $permintaan->AmbilLogo(), $permintaan->boolean('HapusLogo'));

        return redirect()->route('kelola.pengaturan.profil-usaha')->with('Kilat', 'Profil usaha disimpan.');
    }

    public function UnduhLogo(ProfilTenant $profilTenant, PenyimpanLogoTenant $penyimpan): StreamedResponse
    {
        $path = $profilTenant->Ambil($this->IdTenant())['PathLogo'];
        abort_if($path === null, 404);

        return $penyimpan->Unduh($path);
    }

    /**
     * Outlet pembawa alamat & kota profil usaha: outlet yang ditunjuk progres panduan, kalau tidak ada Outlet Utama.
     * Sama dengan outlet yang dipakai langkah panduan awal, supaya kedua halaman mengubah baris yang sama.
     */
    private function OutletProfil(): Outlet
    {
        $outlet = app(ProgresPanduan::class)->AmbilOutlet();
        abort_if($outlet === null, 404);
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($outlet->id, $boleh, true), 404);

        return app(OutletUtama::class)->CariAktif($outlet->id) ?? abort(404);
    }
}
