<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Pengelola\Referensi\Aksi\SimpanWilayah;
use App\Domain\Pengelola\Referensi\Kueri\DaftarReferensi;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Model\Wilayah;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanWilayahPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Data wilayah resmi (P-02). Muat massal lewat perintah `pengelola:impor-wilayah`.
 */
final class WilayahKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan, DaftarReferensi $kueri): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarReferensi::KOLOM_URUT_WILAYAH, 'Kode', DaftarReferensi::KOLOM_SARING_WILAYAH);

        return ResponsTabel::Kirim($permintaan, 'Pengelola/Referensi/Wilayah', 'Wilayah', fn (): array => $kueri->AmbilTabelWilayah($tabel), fn (): array => [
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
