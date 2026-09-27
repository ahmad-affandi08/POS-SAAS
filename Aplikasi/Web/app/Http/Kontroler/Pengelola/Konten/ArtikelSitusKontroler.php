<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Konten;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Pengelola\Konten\Aksi\HapusArtikelSitus;
use App\Domain\Pengelola\Konten\Aksi\SimpanArtikelSitus;
use App\Domain\Pengelola\Konten\Aksi\UbahStatusArtikelSitus;
use App\Domain\Pengelola\Konten\Kueri\DaftarArtikelSitus;
use App\Domain\Pengelola\Konten\Kueri\DaftarKontenSitus;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Situs\Enum\StatusArtikel;
use App\Domain\Situs\Model\ArtikelSitus;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Situs bagian B2: artikel blog di konsol. Lihat: `situs.lihat`; tulis, terbitkan, tarik, hapus: `situs.kelola`.
 */
final class ArtikelSitusKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan, DaftarArtikelSitus $daftar): JsonResponse|Response
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarArtikelSitus::KOLOM_URUT, '', DaftarArtikelSitus::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Pengelola/Situs/Artikel/Daftar', 'Artikel', fn (): array => $daftar->AmbilTabel($tabel), fn (): array => [
            'PilihanKategori' => $daftar->AmbilKategori(),
            'UrlBlog' => AlamatDomain::BuatUrlAbsolutPemasaran('/blog'),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function Buat(Request $permintaan, SimpanArtikelSitus $simpan): RedirectResponse
    {
        $valid = $permintaan->validate(['Judul' => ['required', 'string', 'max:150']], attributes: ['Judul' => 'judul']);
        $artikel = $simpan->Jalankan($this->AmbilPelaku(), [
            'Slug' => null, 'Judul' => (string) $valid['Judul'], 'Ringkasan' => null, 'Isi' => '', 'Kategori' => null,
            'NamaPenulis' => null, 'UuidGambarSampul' => null, 'JudulSeo' => null, 'DeskripsiSeo' => null,
        ]);

        return redirect()->route('pengelola.situs.artikel.ubah', $artikel->Uuid)->with('Kilat', 'Artikel dibuat sebagai draf. Tulis isinya lalu terbitkan.');
    }

    public function Ubah(string $artikelSitus, DaftarArtikelSitus $daftar, DaftarKontenSitus $konten): Response
    {
        $artikel = $this->AmbilArtikel($artikelSitus);

        return Inertia::render('Pengelola/Situs/Artikel/Ubah', [
            'Artikel' => $daftar->PetakanLengkap($artikel),
            'PilihanKategori' => $daftar->AmbilKategori(),
            'Gambar' => $konten->AmbilGambar(),
            'UrlArtikel' => AlamatDomain::BuatUrlAbsolutPemasaran('/blog/'.$artikel->Slug),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function Simpan(Request $permintaan, string $artikelSitus, SimpanArtikelSitus $simpan): RedirectResponse
    {
        $artikel = $this->AmbilArtikel($artikelSitus);
        $valid = $permintaan->validate([
            'Slug' => ['nullable', 'string', 'max:120'],
            'Judul' => ['required', 'string', 'max:150'],
            'Ringkasan' => ['nullable', 'string', 'max:300'],
            'Isi' => ['nullable', 'string', 'max:60000'],
            'Kategori' => ['nullable', 'string', 'max:60'],
            'NamaPenulis' => ['nullable', 'string', 'max:80'],
            'UuidGambarSampul' => ['nullable', 'string', 'size:26'],
            'JudulSeo' => ['nullable', 'string', 'max:70'],
            'DeskripsiSeo' => ['nullable', 'string', 'max:170'],
        ], attributes: ['Judul' => 'judul', 'Ringkasan' => 'ringkasan', 'Isi' => 'isi', 'JudulSeo' => 'judul SEO', 'DeskripsiSeo' => 'deskripsi SEO']);

        if ($artikel->CekTerbit() && trim((string) ($valid['Isi'] ?? '')) === '') {
            return back()->withErrors(['Isi' => 'Artikel yang sudah terbit wajib punya isi.']);
        }

        $simpan->Jalankan($this->AmbilPelaku(), [
            'Slug' => $valid['Slug'] ?? null,
            'Judul' => (string) $valid['Judul'],
            'Ringkasan' => $valid['Ringkasan'] ?? null,
            'Isi' => (string) ($valid['Isi'] ?? ''),
            'Kategori' => $valid['Kategori'] ?? null,
            'NamaPenulis' => $valid['NamaPenulis'] ?? null,
            'UuidGambarSampul' => $valid['UuidGambarSampul'] ?? null,
            'JudulSeo' => $valid['JudulSeo'] ?? null,
            'DeskripsiSeo' => $valid['DeskripsiSeo'] ?? null,
        ], $artikel);

        return back()->with('Kilat', $artikel->CekTerbit() ? 'Artikel disimpan dan langsung berubah di situs.' : 'Draf artikel disimpan.');
    }

    public function Terbitkan(string $artikelSitus, UbahStatusArtikelSitus $ubah): RedirectResponse
    {
        $artikel = $this->AmbilArtikel($artikelSitus);

        if (trim($artikel->Isi) === '') {
            return back()->withErrors(['Isi' => 'Tulis isi artikel dan simpan dulu sebelum menerbitkan.']);
        }

        $ubah->Jalankan($this->AmbilPelaku(), $artikel, StatusArtikel::Terbit);

        return back()->with('Kilat', "Artikel {$artikel->Judul} diterbitkan.");
    }

    public function Tarik(string $artikelSitus, UbahStatusArtikelSitus $ubah): RedirectResponse
    {
        $ubah->Jalankan($this->AmbilPelaku(), $this->AmbilArtikel($artikelSitus), StatusArtikel::Draf);

        return back()->with('Kilat', 'Artikel ditarik ke draf dan tidak tampil lagi di situs.');
    }

    public function Hapus(string $artikelSitus, HapusArtikelSitus $hapus): RedirectResponse
    {
        $hapus->Jalankan($this->AmbilPelaku(), $this->AmbilArtikel($artikelSitus));

        return redirect()->route('pengelola.situs.artikel.daftar')->with('Kilat', 'Artikel dihapus.');
    }

    private function AmbilArtikel(string $uuid): ArtikelSitus
    {
        return ArtikelSitus::query()->where('Uuid', $uuid)->firstOrFail();
    }

    /**
     * @return array{Kelola: bool}
     */
    private function AmbilIzin(): array
    {
        return ['Kelola' => $this->AmbilPelaku()->PunyaIzin(IzinPengelola::SitusKelola)];
    }
}
