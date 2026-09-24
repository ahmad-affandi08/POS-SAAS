<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Katalog\Harga\Kueri\PemakaianKelompokPajak;
use App\Domain\Pajak\Aksi\SimpanKelompokPajak;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\Pajak\Model\KelompokPajak;
use App\Http\Permintaan\Kelola\Katalog\SimpanKelompokPajakPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelompok pajak F-03 (E.8, §12.2): daftar dengan kategori pajak produk & jumlah produk, buat dan ubah (izin
 * `akuntansi.kelola`). Tanpa angka tarif (CLAUDE.md #12). Kelompok dicari lewat ULID di tenant aktif.
 */
final class KelompokPajakKontroler extends DasarKatalogKontroler
{
    public function Daftar(DaftarKelompokPajak $daftar, PemakaianKelompokPajak $pemakaian): Response
    {
        return Inertia::render('Kelola/KelompokPajak/Daftar', [
            ...$daftar->AmbilUntukHalaman($pemakaian->HitungPerKelompok()),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Simpan(SimpanKelompokPajakPermintaan $permintaan, SimpanKelompokPajak $simpan): RedirectResponse
    {
        $kelompok = $simpan->Jalankan(null, $permintaan->AmbilNama(), $permintaan->AmbilKategori(), $permintaan->AmbilPajak());

        return back()->with('Kilat', "Kelompok pajak {$kelompok->Nama} ditambahkan.");
    }

    public function Ubah(string $kelompokPajak, SimpanKelompokPajakPermintaan $permintaan, SimpanKelompokPajak $simpan): RedirectResponse
    {
        $kelompok = KelompokPajak::query()->where('Uuid', $kelompokPajak)->firstOrFail();
        $kelompok = $simpan->Jalankan($kelompok, $permintaan->AmbilNama(), $permintaan->AmbilKategori(), $permintaan->AmbilPajak());

        return back()->with('Kilat', "Kelompok pajak {$kelompok->Nama} disimpan.");
    }
}
