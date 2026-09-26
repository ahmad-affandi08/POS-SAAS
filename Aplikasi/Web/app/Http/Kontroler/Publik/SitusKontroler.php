<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Situs\Kueri\DaftarHalamanSitusPublik;
use App\Domain\Situs\Kueri\PenyusunHalamanSitus;
use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\Response as ResponsHttp;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Situs pemasaran publik (D-21): halaman berblok dari konsol, gambar pustaka, dan peta situs XML (`/peta-situs`).
 */
final class SitusKontroler extends Kontroler
{
    public function Beranda(PenyusunHalamanSitus $penyusun): Response
    {
        return $this->Tampilkan($penyusun, HalamanSitus::SLUG_BERANDA);
    }

    public function Halaman(string $slugHalaman, PenyusunHalamanSitus $penyusun): Response
    {
        abort_if($slugHalaman === HalamanSitus::SLUG_BERANDA, 404);

        return $this->Tampilkan($penyusun, $slugHalaman);
    }

    /** Pratinjau draf dari konsol lewat tautan bertanda tangan (tanpa sesi pengelola di domain pemasaran). */
    public function Pratinjau(HalamanSitus $halamanSitus, PenyusunHalamanSitus $penyusun): Response
    {
        // `Halaman.Pratinjau` = true: view root menambahkan meta robots noindex.
        return Inertia::render('Situs/Halaman', ['Halaman' => $penyusun->AmbilDraf($halamanSitus)]);
    }

    public function Gambar(GambarSitus $gambarSitus): StreamedResponse
    {
        $disk = Storage::disk((string) config('situs.Disk'));
        abort_unless($disk->exists($gambarSitus->Path), 404);

        return $disk->response($gambarSitus->Path, null, [
            'Content-Type' => $gambarSitus->TipeMime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function PetaSitus(DaftarHalamanSitusPublik $daftar): ResponsHttp
    {
        $baris = [];

        foreach ($daftar->Ambil() as $halaman) {
            $jalur = $halaman['Slug'] === HalamanSitus::SLUG_BERANDA ? '/' : '/'.$halaman['Slug'];
            $baris[] = '  <url><loc>'.htmlspecialchars(AlamatDomain::BuatUrlAbsolutPemasaran($jalur), ENT_XML1).'</loc>'
                .($halaman['DiubahPada'] !== null ? '<lastmod>'.$halaman['DiubahPada'].'</lastmod>' : '')
                .'</url>';
        }

        $isi = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .implode("\n", $baris)."\n</urlset>\n";

        return response($isi, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function Tampilkan(PenyusunHalamanSitus $penyusun, string $slug): Response
    {
        $halaman = $penyusun->AmbilTerbit($slug);
        abort_if($halaman === null, 404);

        return Inertia::render('Situs/Halaman', ['Halaman' => $halaman]);
    }
}
