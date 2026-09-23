<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Aksi\SimpanReferensiBank;
use App\Domain\Pengelola\Referensi\Kueri\DaftarReferensi;
use App\Domain\Referensi\Enum\JenisReferensiBank;
use App\Domain\Referensi\Model\ReferensiBank;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanReferensiBankPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Referensi pembayaran: bank, dompet digital, jaringan EDC, penerbit QRIS (P-02).
 */
final class ReferensiBankKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarReferensi $kueri): Response
    {
        return Inertia::render('Pengelola/Referensi/Bank', [
            'Referensi' => array_map(fn (ReferensiBank $referensi): array => [
                'Kode' => $referensi->Kode,
                'Nama' => $referensi->Nama,
                'Jenis' => $referensi->Jenis->value,
                'Aktif' => $referensi->Aktif,
            ], $kueri->AmbilSemuaBank()),
            'PilihanJenis' => array_map(
                fn (JenisReferensiBank $jenis) => ['Nilai' => $jenis->value, 'Label' => $jenis->AmbilLabel()],
                JenisReferensiBank::cases(),
            ),
        ]);
    }

    public function Simpan(SimpanReferensiBankPermintaan $permintaan, SimpanReferensiBank $simpan): RedirectResponse
    {
        $referensi = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "{$referensi->Nama} ditambahkan.");
    }

    public function Ubah(ReferensiBank $referensiBank, SimpanReferensiBankPermintaan $permintaan, SimpanReferensiBank $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $referensiBank);

        return back()->with('Kilat', "{$referensiBank->Nama} diperbarui.");
    }
}
