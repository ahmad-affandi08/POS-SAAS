<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\HapusAkun;
use App\Domain\Akuntansi\Aksi\TambahAkun;
use App\Domain\Akuntansi\Aksi\UbahAkun;
use App\Domain\Akuntansi\Aksi\UbahStatusAkun;
use App\Domain\Akuntansi\Kueri\DaftarBaganAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Http\Permintaan\Kelola\Akuntansi\SimpanAkunPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bagan akun (F-13a, FIN-01): lihat `laporan.keuangan.lihat`, ubah `akuntansi.kelola`. Akun dicari lewat Uuid di
 * tenant aktif (akun tenant lain = 404).
 */
final class BaganAkunKontroler extends DasarAkuntansiKontroler
{
    public function Daftar(DaftarBaganAkun $daftar): Response
    {
        return Inertia::render('Kelola/Akuntansi/Akun/Daftar', [...$daftar->Ambil(), 'Izin' => ['Kelola' => $this->CekIzinKelola()]]);
    }

    public function Simpan(SimpanAkunPermintaan $permintaan, TambahAkun $tambah): RedirectResponse
    {
        $akun = $tambah->Jalankan($permintaan->AmbilData());

        return to_route('kelola.akuntansi.akun.daftar')->with('Kilat', "Akun {$akun->Kode} {$akun->Nama} ditambahkan.");
    }

    public function Perbarui(string $akun, SimpanAkunPermintaan $permintaan, UbahAkun $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->Cari($akun), $permintaan->AmbilData());

        return to_route('kelola.akuntansi.akun.daftar')->with('Kilat', "Akun {$hasil->Kode} {$hasil->Nama} disimpan.");
    }

    public function UbahStatus(string $akun, Request $permintaan, UbahStatusAkun $ubah): RedirectResponse
    {
        $permintaan->validate(['Aktif' => ['required', 'boolean']]);
        $hasil = $ubah->Jalankan($this->Cari($akun), $permintaan->boolean('Aktif'));

        return to_route('kelola.akuntansi.akun.daftar')->with('Kilat', $hasil->Aktif ? "Akun {$hasil->Kode} diaktifkan." : "Akun {$hasil->Kode} dinonaktifkan.");
    }

    public function Hapus(string $akun, HapusAkun $hapus): RedirectResponse
    {
        $satu = $this->Cari($akun);
        $hapus->Jalankan($satu);

        return to_route('kelola.akuntansi.akun.daftar')->with('Kilat', "Akun {$satu->Kode} {$satu->Nama} dihapus.");
    }

    private function Cari(string $uuid): Akun
    {
        return Akun::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
