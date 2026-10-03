<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Lisensi\Aksi\NonaktifkanIntegrasiServer;
use App\Domain\Lisensi\Aksi\SimpanIntegrasiServer;
use App\Domain\Lisensi\Aksi\UjiIntegrasiServer;
use App\Domain\Lisensi\Kontrak\PengaturIntegrasiServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D-35 edisi Lisensi: Pengaturan › Email & WhatsApp server (`/kelola/pengaturan/integrasi-server`, izin
 * `integrasi.api.kelola` khusus Owner). Rutenya hanya didaftarkan di edisi Lisensi. Kredensial tidak pernah dikirim
 * balik ke halaman, hanya petunjuknya.
 */
final class IntegrasiServerKontroler extends DasarKelolaKontroler
{
    public function Daftar(PengaturIntegrasiServer $pengatur): Response
    {
        return Inertia::render('Kelola/Pengaturan/IntegrasiServer', ['Integrasi' => $pengatur->AmbilDaftar()]);
    }

    public function Simpan(Request $permintaan, SimpanIntegrasiServer $simpan): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Jenis' => ['required', 'string', Rule::in(PengaturIntegrasiServer::JENIS)],
            'Penyedia' => ['required', 'string', 'max:60'],
            'Pengaturan' => ['present', 'array'],
            'Kredensial' => ['present', 'array'],
        ], attributes: ['Penyedia' => 'penyedia']);
        $simpan->Jalankan((string) $valid['Jenis'], (string) $valid['Penyedia'], (array) $valid['Pengaturan'], (array) $valid['Kredensial']);

        return back()->with('Kilat', 'Pengaturan disimpan. Uji koneksi untuk mengaktifkannya.');
    }

    public function Uji(string $jenis, UjiIntegrasiServer $uji): RedirectResponse
    {
        // URL huruf kecil (`/integrasi-server/whatsapp/uji`), nilai jenis PascalCase.
        $hasil = $uji->Jalankan(ucfirst($jenis));

        return $hasil['Berhasil']
            ? back()->with('Kilat', "Koneksi berhasil dan sudah aktif. {$hasil['Pesan']}")
            : back()->withErrors(['Umum' => "Koneksi gagal. {$hasil['Pesan']}"]);
    }

    public function Nonaktifkan(string $jenis, NonaktifkanIntegrasiServer $nonaktifkan): RedirectResponse
    {
        $nonaktifkan->Jalankan(ucfirst($jenis));

        return back()->with('Kilat', 'Integrasi dinonaktifkan.');
    }
}
