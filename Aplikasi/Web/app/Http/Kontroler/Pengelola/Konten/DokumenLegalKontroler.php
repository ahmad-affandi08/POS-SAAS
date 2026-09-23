<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Konten;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Konten\Aksi\HapusDrafDokumenLegal;
use App\Domain\Pengelola\Konten\Aksi\SimpanDrafDokumenLegal;
use App\Domain\Pengelola\Konten\Aksi\TerbitkanDokumenLegal;
use App\Domain\Pengelola\Konten\Kueri\DaftarDokumenLegal;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Konten\SimpanDokumenLegalPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dokumen legal berversi (P-06).
 */
final class DokumenLegalKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarDokumenLegal $kueri): Response
    {
        // Draf belum publik: hanya penyusun (legal.kelola) yang melihatnya.
        return Inertia::render('Pengelola/Legal/Daftar', ['Dokumen' => $kueri->Ambil($this->CekBolehLihatDraf())]);
    }

    public function Tampilkan(DokumenLegal $dokumenLegal): Response
    {
        abort_if($dokumenLegal->Status === StatusDokumenLegal::Draf && ! $this->CekBolehLihatDraf(), 404);

        return Inertia::render('Pengelola/Legal/Dokumen', [
            'Dokumen' => [...DaftarDokumenLegal::Petakan($dokumenLegal), 'Isi' => $dokumenLegal->Isi, 'Label' => $dokumenLegal->Jenis->AmbilLabel()],
        ]);
    }

    /** Draf baru menyalin judul & isi versi terakhir jenis itu agar penyusun cukup mengubah bagian yang berganti. */
    public function BuatDraf(Request $permintaan, DaftarDokumenLegal $kueri, SimpanDrafDokumenLegal $simpan): RedirectResponse
    {
        $jenis = JenisDokumenLegal::from($permintaan->validate(['Jenis' => ['required', 'string', Rule::enum(JenisDokumenLegal::class)]])['Jenis']);
        $dokumen = $simpan->Jalankan($this->AmbilPelaku(), $kueri->AmbilDasarDrafBaru($jenis));

        return redirect()
            ->route('pengelola.legal.tampil', $dokumen)
            ->with('Kilat', "Draf {$dokumen->Jenis->AmbilLabel()} versi {$dokumen->Versi} dibuat.");
    }

    public function Ubah(DokumenLegal $dokumenLegal, SimpanDokumenLegalPermintaan $permintaan, SimpanDrafDokumenLegal $simpan): RedirectResponse
    {
        $data = $permintaan->AmbilData();

        if ($data->jenis !== $dokumenLegal->Jenis) {
            throw new PelanggaranAturanBisnis('JenisTidakBisaDiubah', 'Jenis dokumen tidak bisa diubah.', 'Jenis');
        }

        $simpan->Jalankan($this->AmbilPelaku(), $data, $dokumenLegal);

        return back()->with('Kilat', 'Draf disimpan.');
    }

    public function Terbitkan(DokumenLegal $dokumenLegal, TerbitkanDokumenLegal $terbitkan): RedirectResponse
    {
        $dokumen = $terbitkan->Jalankan($this->AmbilPelaku(), $dokumenLegal);

        return back()->with('Kilat', "{$dokumen->Jenis->AmbilLabel()} versi {$dokumen->Versi} terbit, berlaku mulai {$dokumen->BerlakuMulai->translatedFormat('j F Y')}.");
    }

    public function Hapus(DokumenLegal $dokumenLegal, HapusDrafDokumenLegal $hapus): RedirectResponse
    {
        $hapus->Jalankan($this->AmbilPelaku(), $dokumenLegal);

        return redirect()->route('pengelola.legal.daftar')->with('Kilat', 'Draf dihapus.');
    }

    private function CekBolehLihatDraf(): bool
    {
        return $this->AmbilPelaku()->PunyaIzin(IzinPengelola::LegalKelola);
    }
}
