<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Aksi\SimpanWilayah;
use App\Domain\Pengelola\Referensi\Kueri\DaftarReferensi;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Model\Wilayah;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanWilayahPermintaan;
use App\Http\Respons\DaftarBerhalaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data wilayah resmi (P-02). Muat massal lewat perintah `pengelola:impor-wilayah`.
 */
final class WilayahKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan, DaftarReferensi $kueri): Response
    {
        $kata = trim($permintaan->string('kata')->toString());
        $tingkat = TingkatWilayah::tryFrom($permintaan->string('tingkat')->toString());

        return Inertia::render('Pengelola/Referensi/Wilayah', [
            'Wilayah' => DaftarBerhalaman::Buat($kueri->CariWilayah($kata, $tingkat), fn (Wilayah $wilayah): array => [
                'Kode' => $wilayah->Kode,
                'Nama' => $wilayah->Nama,
                'Tingkat' => $wilayah->Tingkat->value,
                'KodeInduk' => $wilayah->KodeInduk,
                'ZonaWaktu' => $wilayah->ZonaWaktu->value,
            ]),
            'Saring' => ['Kata' => $kata, 'Tingkat' => $tingkat?->value],
            'PilihanTingkat' => array_map(fn (TingkatWilayah $item) => ['Nilai' => $item->value, 'Label' => $item->AmbilLabel()], TingkatWilayah::cases()),
            'PilihanZonaWaktu' => array_map(fn (ZonaWaktu $item) => $item->value, ZonaWaktu::cases()),
        ]);
    }

    public function Simpan(SimpanWilayahPermintaan $permintaan, SimpanWilayah $simpan): RedirectResponse
    {
        $wilayah = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Wilayah {$wilayah->Nama} ditambahkan.");
    }

    public function Ubah(Wilayah $wilayah, SimpanWilayahPermintaan $permintaan, SimpanWilayah $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $wilayah);

        return back()->with('Kilat', "Wilayah {$wilayah->Nama} diperbarui.");
    }
}
