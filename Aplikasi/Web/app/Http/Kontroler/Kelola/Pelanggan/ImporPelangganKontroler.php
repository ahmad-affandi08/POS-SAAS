<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pelanggan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Impor\Layanan\PembacaBerkasTabel;
use App\Domain\Katalog\Impor\Layanan\PenulisTabel;
use App\Domain\Pelanggan\Aksi\ImporPelanggan;
use App\Domain\Pelanggan\Kueri\DaftarPelanggan;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor & ekspor pelanggan (F-16a, v3.36), izin `pelanggan.kelola` karena memuat data pribadi (UU PDP). Impor dua
 * langkah: periksa (tanpa menyimpan) lalu terapkan berkas yang sama; hasilnya dikirim lewat sesi kilat ke halaman.
 */
final class ImporPelangganKontroler extends DasarKelolaKontroler
{
    /** Judul kolom ekspor; enam kolom pertama (+ Tag, Catatan, SetujuPemasaran) bisa diimpor ulang apa adanya. */
    private const JUDUL_EKSPOR = ['Nama', 'NoHp', 'Email', 'TanggalLahir', 'Alamat', 'Tag', 'Catatan', 'SetujuPemasaran', 'Tier', 'Poin', 'SaldoDeposit', 'Status', 'TerdaftarPada'];

    public function Halaman(Request $permintaan): Response
    {
        $hasil = $permintaan->session()->get('HasilImporPelanggan');

        return Inertia::render('Kelola/Pelanggan/Impor', [
            'Hasil' => is_array($hasil) ? $hasil : null,
            'MaksimalBaris' => ImporPelanggan::MAKSIMAL_BARIS,
        ]);
    }

    public function Kirim(Request $permintaan, ImporPelanggan $impor): RedirectResponse
    {
        $data = $permintaan->validate([
            'Berkas' => ['required', 'file', 'max:5120'],
            'Terapkan' => ['sometimes', 'boolean'],
        ], [
            'Berkas.required' => 'Pilih berkas Excel (.xlsx) atau CSV.',
            'Berkas.max' => 'Ukuran berkas paling besar 5 MB.',
            'Berkas.*' => 'Berkas tidak bisa dibaca. Unggah ulang.',
        ]);
        /** @var UploadedFile $berkas */
        $berkas = $data['Berkas'];
        $terapkan = (bool) ($data['Terapkan'] ?? false);
        $hasil = $impor->Jalankan((string) $berkas->getRealPath(), $berkas->getClientOriginalName(), $terapkan, $this->Pelaku()->Id);
        $kembali = to_route('kelola.pelanggan.impor')->with('HasilImporPelanggan', [...$hasil, 'NamaBerkas' => mb_substr($berkas->getClientOriginalName(), 0, 120)]);

        return $terapkan ? $kembali->with('Kilat', "{$hasil['Baru']} pelanggan baru diimpor.") : $kembali;
    }

    public function Templat(): StreamedResponse
    {
        return PenulisTabel::Alirkan(PembacaBerkasTabel::FORMAT_XLSX, 'TemplatImporPelanggan', ['Nama', 'NoHp', 'Email', 'TanggalLahir', 'Alamat', 'Tag', 'Catatan', 'SetujuPemasaran'], fn (): array => [
            ['Siti Rahmawati', '0812-3456-7890', 'siti@contoh.id', '1990-08-17', 'Jl. Melati No. 12, Sukoharjo', 'Member; Arisan', 'Suka kopi tanpa gula', 'Ya'],
        ]);
    }

    /** Ekspor dengan saringan & cari yang sedang aktif di tabel; `?format=csv` untuk CSV (bawaan Excel). */
    public function Ekspor(Request $permintaan, DaftarPelanggan $daftar, PencatatAudit $audit): StreamedResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPelanggan::KOLOM_URUT, DaftarPelanggan::URUT_BAWAAN, DaftarPelanggan::KOLOM_SARING);
        $format = $permintaan->query('format') === PembacaBerkasTabel::FORMAT_CSV ? PembacaBerkasTabel::FORMAT_CSV : PembacaBerkasTabel::FORMAT_XLSX;
        // Ekspor data pribadi dicatat (siapa & saringannya), tanpa isi datanya.
        $audit->Catat('pelanggan.ekspor', null, nilaiBaru: ['Format' => $format, 'Cari' => $tabel->cari !== '', 'Saring' => array_keys($tabel->saring)], idPengguna: $this->Pelaku()->Id);

        return PenulisTabel::Alirkan($format, 'Pelanggan-'.now()->format('Ymd'), self::JUDUL_EKSPOR, fn () => $daftar->AlirkanEkspor($tabel));
    }
}
