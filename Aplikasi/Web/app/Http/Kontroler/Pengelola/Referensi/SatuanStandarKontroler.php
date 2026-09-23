<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Aksi\SimpanSatuanStandar;
use App\Domain\Pengelola\Referensi\Kueri\DaftarReferensi;
use App\Domain\Referensi\Model\SatuanStandar;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanSatuanStandarPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satuan standar platform (P-02).
 */
final class SatuanStandarKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarReferensi $kueri): Response
    {
        return Inertia::render('Pengelola/Referensi/Satuan', [
            'Satuan' => array_map(fn (SatuanStandar $satuan): array => [
                'Kode' => $satuan->Kode,
                'Nama' => $satuan->Nama,
                'Simbol' => $satuan->Simbol,
                'BolehDesimal' => $satuan->BolehDesimal,
                'Aktif' => $satuan->Aktif,
            ], $kueri->AmbilSemuaSatuan()),
        ]);
    }

    public function Simpan(SimpanSatuanStandarPermintaan $permintaan, SimpanSatuanStandar $simpan): RedirectResponse
    {
        $satuan = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Satuan {$satuan->Nama} ditambahkan.");
    }

    public function Ubah(SatuanStandar $satuanStandar, SimpanSatuanStandarPermintaan $permintaan, SimpanSatuanStandar $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $satuanStandar);

        return back()->with('Kilat', "Satuan {$satuanStandar->Nama} diperbarui.");
    }
}
