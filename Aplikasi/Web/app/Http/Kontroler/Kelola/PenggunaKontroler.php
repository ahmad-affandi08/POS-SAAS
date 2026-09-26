<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\BatalkanUndangan;
use App\Domain\Organisasi\Aksi\TambahPengguna;
use App\Domain\Organisasi\Aksi\UbahAksesAnggota;
use App\Domain\Organisasi\Aksi\UbahStatusAnggota;
use App\Domain\Organisasi\Aksi\UndangPengguna;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PemakaianBatasOrganisasi;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Organisasi\Model\UndanganPengguna;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Http\Permintaan\Kelola\AksesAnggotaPermintaan;
use App\Http\Permintaan\Kelola\TambahPenggunaPermintaan;
use App\Http\Permintaan\Kelola\UndangPenggunaPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengguna tenant: daftar anggota, tambah langsung (D-22) atau undangan, peran & akses outlet, nonaktif/aktif
 * (F-02 langkah 3, BR-02.1).
 * Anggota dicari lewat `Uuid` pengguna di dalam tenant aktif; pengguna yang bukan anggota tenant ini → 404.
 */
final class PenggunaKontroler extends DasarKelolaKontroler
{
    public function Daftar(DaftarAnggota $daftar, PemakaianBatasOrganisasi $pemakaian, PastikanBatasPaket $batasPaket): Response
    {
        $idTenant = $this->IdTenant();

        return Inertia::render('Kelola/Pengguna/Daftar', [
            'Anggota' => $daftar->AmbilAnggota($idTenant),
            'Undangan' => $daftar->AmbilUndanganMenunggu($idTenant),
            'Peran' => self::AmbilOpsiPeran(),
            'Outlet' => self::AmbilOpsiOutlet(),
            'BatasPengguna' => $batasPaket->AmbilRingkasan($idTenant, 'BatasPengguna', $pemakaian->HitungPengguna($idTenant)),
            'UuidSaya' => $this->Pelaku()->Uuid,
        ]);
    }

    /** D-22: halaman "Tambah pengguna" (langsung, tanpa undangan email); kursi paket tetap ditampilkan. */
    public function Buat(PemakaianBatasOrganisasi $pemakaian, PastikanBatasPaket $batasPaket): Response
    {
        $idTenant = $this->IdTenant();

        return Inertia::render('Kelola/Pengguna/Tambah', [
            'Peran' => self::AmbilOpsiPeran(),
            'Outlet' => self::AmbilOpsiOutlet(),
            'BatasPengguna' => $batasPaket->AmbilRingkasan($idTenant, 'BatasPengguna', $pemakaian->HitungPengguna($idTenant)),
        ]);
    }

    public function Tambah(TambahPenggunaPermintaan $permintaan, TambahPengguna $tambah): RedirectResponse
    {
        $data = $permintaan->AmbilPenggunaBaru();
        $pengguna = $tambah->Jalankan($this->Pelaku(), $data, $permintaan->AmbilAkses());
        $pesan = $pengguna->CekHanyaKasir()
            ? "{$pengguna->Nama} ditambahkan sebagai karyawan kasir. Ia masuk aplikasi kasir dengan PIN yang Anda buat."
            : "{$pengguna->Nama} ditambahkan. Berikan email & kata sandi awal kepadanya; ia wajib menggantinya saat pertama masuk.";

        return redirect()->route('kelola.pengguna.daftar')->with('Kilat', $pesan);
    }

    /** Halaman penuh "Undang pengguna" (pola sama dengan Tambah produk); kursi paket tetap ditampilkan. */
    public function BuatUndangan(PemakaianBatasOrganisasi $pemakaian, PastikanBatasPaket $batasPaket): Response
    {
        $idTenant = $this->IdTenant();

        return Inertia::render('Kelola/Pengguna/Buat', [
            'Peran' => self::AmbilOpsiPeran(),
            'Outlet' => self::AmbilOpsiOutlet(),
            'BatasPengguna' => $batasPaket->AmbilRingkasan($idTenant, 'BatasPengguna', $pemakaian->HitungPengguna($idTenant)),
        ]);
    }

    public function Undang(UndangPenggunaPermintaan $permintaan, UndangPengguna $undang, RingkasanTenant $ringkasan): RedirectResponse
    {
        $email = mb_strtolower(trim($permintaan->string('Email')->toString()));
        $namaTenant = $ringkasan->Ambil([$this->IdTenant()])[0]['Nama'] ?? '';
        $undang->Jalankan($this->Pelaku(), $email, $permintaan->AmbilAkses(), $namaTenant);

        return redirect()->route('kelola.pengguna.daftar')->with('Kilat', "Undangan terkirim ke {$email}. Berlaku ".config('organisasi.JamBerlakuUndangan').' jam.');
    }

    public function BatalkanUndangan(string $undangan, BatalkanUndangan $batalkan): RedirectResponse
    {
        $baris = UndanganPengguna::query()->where('IdTenant', $this->IdTenant())->where('Uuid', $undangan)->firstOrFail();
        $batalkan->Jalankan($baris);

        return back()->with('Kilat', "Undangan untuk {$baris->Email} dibatalkan.");
    }

    public function UbahAkses(string $pengguna, AksesAnggotaPermintaan $permintaan, UbahAksesAnggota $ubah): RedirectResponse
    {
        [$anggota, $nama] = $this->CariAnggota($pengguna);
        $ubah->Jalankan($this->Pelaku()->Id, $anggota, $permintaan->AmbilAkses());

        return back()->with('Kilat', "Peran & akses {$nama} disimpan.");
    }

    public function Nonaktifkan(string $pengguna, UbahStatusAnggota $ubah): RedirectResponse
    {
        [$anggota, $nama] = $this->CariAnggota($pengguna);
        $ubah->Jalankan($this->Pelaku()->Id, $anggota, StatusKeanggotaan::Nonaktif);

        return back()->with('Kilat', "{$nama} dinonaktifkan dan langsung keluar dari usaha ini.");
    }

    public function Aktifkan(string $pengguna, UbahStatusAnggota $ubah): RedirectResponse
    {
        [$anggota, $nama] = $this->CariAnggota($pengguna);
        $ubah->Jalankan($this->Pelaku()->Id, $anggota, StatusKeanggotaan::Aktif);

        return back()->with('Kilat', "{$nama} aktif kembali.");
    }

    /**
     * @return array<int, array{Uuid: string, Nama: string, Pemilik: bool, SemuaOutletBawaan: bool}>
     */
    private static function AmbilOpsiPeran(): array
    {
        return Peran::query()->orderByDesc('Bawaan')->orderBy('Id')->get()->map(fn (Peran $peran): array => [
            'Uuid' => $peran->Uuid,
            'Nama' => $peran->Nama,
            'Pemilik' => $peran->CekPemilik(),
            'SemuaOutletBawaan' => $peran->Kode !== null && (PeranTenantBawaan::tryFrom($peran->Kode)?->CekSemuaOutletBawaan() ?? false),
        ])->values()->all();
    }

    /**
     * @return array<int, array{Uuid: string, Kode: string, Nama: string}>
     */
    private static function AmbilOpsiOutlet(): array
    {
        return Outlet::query()->where('Status', StatusOrganisasi::Aktif->value)->orderBy('Nama')->get()
            ->map(fn (Outlet $outlet): array => ['Uuid' => $outlet->Uuid, 'Kode' => $outlet->Kode, 'Nama' => $outlet->Nama])->values()->all();
    }

    /**
     * @return array{0: TenantPengguna, 1: string}
     */
    private function CariAnggota(string $uuidPengguna): array
    {
        $pengguna = Pengguna::query()->where('Uuid', $uuidPengguna)->firstOrFail();
        $anggota = TenantPengguna::query()->where('IdTenant', $this->IdTenant())->where('IdPengguna', $pengguna->Id)->firstOrFail();

        return [$anggota, $pengguna->Nama];
    }
}
