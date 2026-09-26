<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Rilis;

use App\Domain\Pengelola\Rilis\Aksi\SegarkanKompatibilitasPerangkat;
use App\Domain\Pengelola\Rilis\Aksi\TandaiKompatibilitasPerangkat;
use App\Domain\Tenant\Enum\StatusKompatibilitas;
use App\Domain\Tenant\Kueri\DaftarKompatibilitasPerangkat;
use App\Domain\Tenant\Model\KompatibilitasPerangkat;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hardware Compatibility List (PRD §17.2.5a, v1.98): lihat `rilis.lihat`; segarkan & tandai Tersertifikasi/Terbatas
 * `rilis.kelola`.
 */
final class KompatibilitasPerangkatKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarKompatibilitasPerangkat $kueri): Response
    {
        return Inertia::render('Pengelola/Rilis/KompatibilitasPerangkat', ['Baris' => $kueri->Ambil(publik: false)]);
    }

    public function Segarkan(SegarkanKompatibilitasPerangkat $segarkan): RedirectResponse
    {
        $hasil = $segarkan->Jalankan();

        return back()->with('Kilat', "Daftar disegarkan: {$hasil['Perangkat']} model perangkat, {$hasil['Printer']} printer.");
    }

    public function Tandai(KompatibilitasPerangkat $kompatibilitasPerangkat, Request $permintaan, TandaiKompatibilitasPerangkat $tandai): RedirectResponse
    {
        $permintaan->validate([
            'Status' => ['nullable', Rule::in([StatusKompatibilitas::Tersertifikasi->value, StatusKompatibilitas::Terbatas->value])],
            'Catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'Status.*' => 'Pilih Tersertifikasi, Terbatas, atau kembali otomatis.',
            'Catatan.*' => 'Catatan paling panjang 500 karakter.',
        ]);
        $baris = $tandai->Jalankan(
            $this->AmbilPelaku(),
            $kompatibilitasPerangkat,
            $permintaan->filled('Status') ? StatusKompatibilitas::from($permintaan->string('Status')->toString()) : null,
            $permintaan->filled('Catatan') ? $permintaan->string('Catatan')->toString() : null,
        );

        return back()->with('Kilat', "{$baris->Nama}: {$baris->AmbilStatus()->AmbilLabel()}.");
    }
}
