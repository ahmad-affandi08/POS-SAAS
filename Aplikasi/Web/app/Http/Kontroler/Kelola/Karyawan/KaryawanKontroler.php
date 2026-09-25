<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Karyawan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Karyawan\Aksi\SimpanKaryawan;
use App\Domain\Karyawan\Aksi\UbahStatusKaryawan;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Kueri\DaftarKaryawan;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Karyawan\SimpanKaryawanPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Data karyawan (F-18, EMP-01, `/kelola/karyawan`): daftar, tambah/ubah (panel), nonaktifkan/aktifkan. Lihat
 * `karyawan.lihat`; ubah `karyawan.kelola`. Karyawan tenant lain = 404 (`MilikTenant`).
 */
final class KaryawanKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarKaryawan $daftar, DaftarAnggota $anggota, PetaUuidOutlet $outlet): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarKaryawan::KOLOM_URUT, DaftarKaryawan::URUT_BAWAAN, DaftarKaryawan::KOLOM_SARING);
        $kelola = $this->CekKelola();

        return ResponsTabel::Kirim($permintaan, 'Kelola/Karyawan/Daftar', 'Karyawan', fn (): array => $daftar->Ambil($tabel, $kelola), fn (): array => [
            'OpsiPengguna' => $kelola ? array_map(fn (array $a): array => ['Uuid' => $a['Uuid'], 'Nama' => $a['Nama']], $anggota->AmbilPilihanAktif($this->IdTenant())) : [],
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh(), true)),
            'Izin' => ['Kelola' => $kelola],
        ]);
    }

    public function Simpan(SimpanKaryawanPermintaan $permintaan, SimpanKaryawan $simpan): RedirectResponse
    {
        $k = $simpan->Jalankan($permintaan->AmbilData($this->Pelaku()->Id));

        return back()->with('Kilat', "Karyawan {$k->Nama} ditambahkan.");
    }

    public function Perbarui(SimpanKaryawanPermintaan $permintaan, string $karyawan, SimpanKaryawan $simpan): RedirectResponse
    {
        $k = $simpan->Jalankan($permintaan->AmbilData($this->Pelaku()->Id), $this->Cari($karyawan));

        return back()->with('Kilat', "Karyawan {$k->Nama} disimpan.");
    }

    public function Nonaktifkan(string $karyawan, UbahStatusKaryawan $ubah): RedirectResponse
    {
        $k = $ubah->Jalankan($this->Cari($karyawan), StatusKaryawan::Nonaktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "{$k->Nama} dinonaktifkan. Ia tidak bisa absen lagi.");
    }

    public function Aktifkan(string $karyawan, UbahStatusKaryawan $ubah): RedirectResponse
    {
        $k = $ubah->Jalankan($this->Cari($karyawan), StatusKaryawan::Aktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "{$k->Nama} diaktifkan kembali.");
    }

    private function Cari(string $uuid): Karyawan
    {
        return Karyawan::query()->where('Uuid', $uuid)->firstOrFail();
    }

    private function CekKelola(): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::KaryawanKelola);
    }
}
