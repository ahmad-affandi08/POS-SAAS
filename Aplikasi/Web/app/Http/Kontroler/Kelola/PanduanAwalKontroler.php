<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\PanduanAwal\Aksi\KonfirmasiPajakPanduan;
use App\Domain\PanduanAwal\Aksi\SelesaikanPanduanAwal;
use App\Domain\PanduanAwal\Aksi\SimpanProfilUsaha;
use App\Domain\PanduanAwal\Aksi\TandaiLangkahPanduan;
use App\Domain\PanduanAwal\Aksi\TerapkanTemplateSektor;
use App\Domain\PanduanAwal\Data\HasilPenerapanTemplate;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\PanduanAwal\Kueri\PilihanTemplate;
use App\Domain\PanduanAwal\Kueri\UsulanPajak;
use App\Domain\Referensi\Kueri\WilayahKota;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Http\Permintaan\Kelola\PanduanAwal\SimpanPajakPanduanPermintaan;
use App\Http\Permintaan\Kelola\PanduanAwal\SimpanProfilUsahaPermintaan;
use App\Http\Permintaan\Kelola\PanduanAwal\TerapkanTemplateSektorPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F-01 Panduan awal: ringkasan, langkah 1 (profil usaha), 2 (template sektor), 3 (pajak), lewati/selesai per langkah,
 * dan selesaikan panduan. Kontroler tipis: validasi di Permintaan, logika di Aksi/Kueri domain.
 */
final class PanduanAwalKontroler extends DasarPanduanAwalKontroler
{
    public function Indeks(): Response
    {
        $this->OutletPanduan();

        return Inertia::render('Kelola/PanduanAwal/Indeks', ['Progres' => $this->Progres()]);
    }

    public function TampilkanProfilUsaha(ProfilTenant $profilTenant, WilayahKota $wilayahKota): Response
    {
        $outlet = $this->OutletPanduan();
        $profil = $profilTenant->Ambil($this->IdTenant());

        return Inertia::render('Kelola/PanduanAwal/ProfilUsaha', [
            'Progres' => $this->Progres(),
            'Profil' => [
                'NamaUsaha' => $profil['Nama'],
                'Alamat' => $outlet->Alamat,
                'KodeKota' => $outlet->KodeKota,
                'Npwp' => $profil['Npwp'],
                'Pkp' => $profil['Pkp'],
                'TautanLogo' => $profil['PathLogo'] === null ? null : route('kelola.panduan-awal.profil-usaha.logo', ['v' => substr(hash('sha256', $profil['PathLogo']), 0, 12)]),
            ],
            'Kota' => array_map(fn (array $kota): array => [
                'Kode' => $kota['Kode'],
                'Nama' => $kota['Nama'],
                'NamaProvinsi' => $kota['NamaProvinsi'],
                'ZonaWaktu' => $kota['ZonaWaktu'],
            ], $wilayahKota->AmbilSemua()),
            'BatasLogo' => ['UkuranMaksimalKb' => (int) config('tenant.UkuranMaksimalLogoKb'), 'Ekstensi' => array_values((array) config('tenant.EkstensiLogo'))],
        ]);
    }

    public function SimpanProfilUsaha(SimpanProfilUsahaPermintaan $permintaan, SimpanProfilUsaha $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->OutletPanduan(), $permintaan->AmbilData(), $permintaan->AmbilLogo(), $permintaan->boolean('HapusLogo'));

        return redirect()->route('kelola.panduan-awal.sektor')->with('Kilat', 'Profil usaha disimpan.');
    }

    public function UnduhLogo(ProfilTenant $profilTenant, PenyimpanLogoTenant $penyimpan): StreamedResponse
    {
        $path = $profilTenant->Ambil($this->IdTenant())['PathLogo'];
        abort_if($path === null, 404);

        return $penyimpan->Unduh($path);
    }

    public function TampilkanSektor(PilihanTemplate $pilihan): Response
    {
        return Inertia::render('Kelola/PanduanAwal/Sektor', [
            'Progres' => $this->Progres(),
            ...$pilihan->Ambil($this->OutletPanduan()),
        ]);
    }

    public function TerapkanSektor(TerapkanTemplateSektorPermintaan $permintaan, TerapkanTemplateSektor $terapkan): RedirectResponse
    {
        $hasil = $terapkan->Jalankan($this->OutletPanduan(), $permintaan->string('KodeTemplate')->toString(), $permintaan->AmbilSektorLain());

        return redirect()->route('kelola.panduan-awal.pajak')->with('Kilat', self::PesanPenerapan($hasil));
    }

    public function TampilkanPajak(UsulanPajak $usulan): Response
    {
        return Inertia::render('Kelola/PanduanAwal/Pajak', [
            'Progres' => $this->Progres(),
            ...$usulan->Ambil($this->OutletPanduan()),
        ]);
    }

    public function SimpanPajak(SimpanPajakPanduanPermintaan $permintaan, KonfirmasiPajakPanduan $konfirmasi): RedirectResponse
    {
        $konfirmasi->Jalankan($this->OutletPanduan(), $permintaan->AmbilData());

        return redirect()->route('kelola.panduan-awal.produk')->with('Kilat', 'Pengaturan pajak disimpan.');
    }

    public function Lewati(string $langkah, TandaiLangkahPanduan $tandai): RedirectResponse
    {
        $kunci = LangkahPanduan::DariSlug($langkah) ?? abort(404);
        $tandai->Jalankan($kunci, StatusLangkahPanduan::Dilewati, $this->OutletPanduan()->Id);

        return $this->KeLangkahBerikutnya($kunci, "Langkah {$kunci->AmbilJudul()} dilewati. Anda bisa kembali kapan saja.");
    }

    public function TandaiSelesai(string $langkah, TandaiLangkahPanduan $tandai): RedirectResponse
    {
        $kunci = LangkahPanduan::DariSlug($langkah) ?? abort(404);
        abort_unless($kunci->CekBisaDitandaiSelesaiLangsung(), 404);
        $tandai->Jalankan($kunci, StatusLangkahPanduan::Selesai, $this->OutletPanduan()->Id);

        return $this->KeLangkahBerikutnya($kunci, "Langkah {$kunci->AmbilJudul()} selesai.");
    }

    public function Selesaikan(SelesaikanPanduanAwal $selesaikan): RedirectResponse
    {
        $selesaikan->Jalankan($this->Pelaku()->Id, $this->OutletPanduan()->Id);

        return redirect()->route('kelola.beranda')->with('Kilat', 'Panduan awal selesai. Lanjutkan dengan langkah berikutnya di bawah.');
    }

    private static function PesanPenerapan(HasilPenerapanTemplate $hasil): string
    {
        $bagian = array_filter([
            $hasil->jumlahAkun > 0 ? "{$hasil->jumlahAkun} akun" : null,
            $hasil->jumlahKategori > 0 ? "{$hasil->jumlahKategori} kategori" : null,
            $hasil->jumlahSatuan > 0 ? "{$hasil->jumlahSatuan} satuan" : null,
            $hasil->jumlahKelompokPajak > 0 ? "{$hasil->jumlahKelompokPajak} kelompok pajak" : null,
        ]);

        if ($bagian === []) {
            return $hasil->versiBerubah
                ? "Template {$hasil->namaTemplate} versi {$hasil->versi} diterapkan. Tidak ada data baru."
                : 'Template sudah diterapkan, tidak ada data baru.';
        }

        return "Template {$hasil->namaTemplate} versi {$hasil->versi} diterapkan: ".implode(', ', $bagian).' ditambahkan.';
    }
}
