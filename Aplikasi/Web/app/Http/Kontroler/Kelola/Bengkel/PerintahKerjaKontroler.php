<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Bengkel;

use App\Domain\Bengkel\Aksi\AturServisBerikutnya;
use App\Domain\Bengkel\Aksi\MintaPersetujuanServis;
use App\Domain\Bengkel\Aksi\PutuskanPersetujuanServis;
use App\Domain\Bengkel\Aksi\SimpanPerintahKerja;
use App\Domain\Bengkel\Aksi\UbahStatusPerintahKerja;
use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Domain\Bengkel\Enum\LewatPersetujuan;
use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Kueri\CariProdukBengkel;
use App\Domain\Bengkel\Kueri\DaftarKendaraan;
use App\Domain\Bengkel\Kueri\DaftarPerintahKerja;
use App\Domain\Bengkel\Kueri\DetailPerintahKerja;
use App\Domain\Bengkel\Kueri\IsianFormPerintahKerja;
use App\Domain\Bengkel\Layanan\TautanPersetujuanServis;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pelanggan\Kueri\DaftarPilihanPelanggan;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Permintaan\Kelola\Bengkel\SimpanPerintahKerjaPermintaan;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perintah kerja bengkel (§9.10, `/kelola/bengkel/perintah-kerja`, izin `bengkel.kelola`, dibatasi outlet akses):
 * daftar (TabelData), formulir (pelanggan, kendaraan, keluhan, jasa + mekanik, sparepart), detail dengan tombol status,
 * minta persetujuan (tautan WhatsApp yang bisa disalin), catat persetujuan langsung, servis berikutnya, dan cetak.
 * Harga tidak pernah datang dari formulir: server mengambilnya dari price engine saat menyimpan.
 */
final class PerintahKerjaKontroler extends DasarBengkelKontroler
{
    public function Daftar(Request $permintaan, DaftarPerintahKerja $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPerintahKerja::KOLOM_URUT, DaftarPerintahKerja::URUT_BAWAAN, DaftarPerintahKerja::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Bengkel/PerintahKerja/Daftar', 'PerintahKerja', fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh(), $this->ZonaTenant(), $this->HariIni()), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusPerintahKerja $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusPerintahKerja::cases()),
            'OpsiOutlet' => $this->OpsiOutlet(),
            'OpsiMekanik' => $this->OpsiMekanik(),
        ]);
    }

    public function Buat(Request $permintaan, IsianFormPerintahKerja $isian): Response
    {
        $kendaraan = $permintaan->query('kendaraan');

        return $this->RenderForm(null, is_string($kendaraan) && $kendaraan !== '' ? $isian->DariKendaraan($kendaraan) : null);
    }

    public function Simpan(SimpanPerintahKerjaPermintaan $permintaan, SimpanPerintahKerja $simpan, ZonaWaktuOutlet $zona): RedirectResponse
    {
        $outlet = $this->CariOutlet((string) $permintaan->validated('UuidOutlet'));
        $pk = $simpan->Jalankan($permintaan->AmbilData($outlet->Id, $zona->Ambil($outlet->Id)), $this->Pelaku()->Id);

        return to_route('kelola.bengkel.perintah-kerja.detail', ['perintahKerja' => $pk->Uuid])
            ->with('Kilat', "{$pk->Nomor} disimpan. Periksa estimasinya, lalu minta persetujuan pelanggan.");
    }

    public function Detail(string $perintahKerja, DetailPerintahKerja $detail): Response
    {
        $pk = $this->CariPerintahKerja($perintahKerja);

        return Inertia::render('Kelola/Bengkel/PerintahKerja/Detail', [
            'PerintahKerja' => $detail->Ambil($pk, $this->SlugTenant(), true),
            'Riwayat' => $detail->AmbilRiwayat($pk),
            'Tindakan' => [
                'Ubah' => $pk->Status->CekBolehDiubah(),
                'MintaPersetujuan' => in_array($pk->Status, [StatusPerintahKerja::Diterima, StatusPerintahKerja::Diagnosis, StatusPerintahKerja::MenungguPersetujuan], true),
                'CatatPersetujuan' => $pk->Status->CekBolehDiputuskan(),
                'TujuanStatus' => array_map(fn (StatusPerintahKerja $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], $pk->Status->AmbilTujuanManual()),
                'AturServis' => $pk->Status !== StatusPerintahKerja::Dibatalkan,
            ],
            'HariIni' => $this->HariIni()->toDateString(),
            'LihatPenjualan' => $this->CekIzin(IzinTenant::LaporanPenjualanLihat),
        ]);
    }

    public function Ubah(string $perintahKerja, IsianFormPerintahKerja $isian): Response|RedirectResponse
    {
        $pk = $this->CariPerintahKerja($perintahKerja);

        if (! $pk->Status->CekBolehDiubah()) {
            return to_route('kelola.bengkel.perintah-kerja.detail', ['perintahKerja' => $pk->Uuid])
                ->withErrors(['Umum' => "Perintah kerja berstatus {$pk->Status->AmbilLabel()} tidak bisa diubah. Kembalikan ke Diagnosis dulu untuk merevisi estimasi."]);
        }

        return $this->RenderForm($isian->DariPerintahKerja($pk), null);
    }

    public function Perbarui(SimpanPerintahKerjaPermintaan $permintaan, string $perintahKerja, SimpanPerintahKerja $simpan, ZonaWaktuOutlet $zona): RedirectResponse
    {
        $pk = $this->CariPerintahKerja($perintahKerja);
        $outlet = $this->CariOutlet((string) $permintaan->validated('UuidOutlet'));
        $simpan->Jalankan($permintaan->AmbilData($outlet->Id, $zona->Ambil($outlet->Id)), $this->Pelaku()->Id, $pk->Uuid);

        return to_route('kelola.bengkel.perintah-kerja.detail', ['perintahKerja' => $pk->Uuid])->with('Kilat', "{$pk->Nomor} disimpan.");
    }

    public function UbahStatus(Request $permintaan, string $perintahKerja, UbahStatusPerintahKerja $ubah): RedirectResponse
    {
        $pk = $this->CariPerintahKerja($perintahKerja);
        $valid = $permintaan->validate([
            'Status' => ['required', Rule::enum(StatusPerintahKerja::class)],
            'Alasan' => ['nullable', 'string', 'max:255'],
            'CatatanQc' => ['nullable', 'string', 'max:500'],
        ]);
        $tujuan = StatusPerintahKerja::from((string) $valid['Status']);
        $ubah->Jalankan($pk->Uuid, $tujuan, $this->Pelaku()->Id, isset($valid['Alasan']) ? (string) $valid['Alasan'] : null, isset($valid['CatatanQc']) ? (string) $valid['CatatanQc'] : null);

        return back()->with('Kilat', "{$pk->Nomor}: {$tujuan->AmbilLabel()}.");
    }

    /** Buat tautan persetujuan (7 hari) dan, bila diminta, kirim lewat WhatsApp toko. Tautan selalu bisa disalin. */
    public function MintaPersetujuan(Request $permintaan, string $perintahKerja, MintaPersetujuanServis $minta): RedirectResponse
    {
        $pk = $this->CariPerintahKerja($perintahKerja);
        $valid = $permintaan->validate(['KirimWhatsapp' => ['sometimes', 'boolean']]);
        $kirim = (bool) ($valid['KirimWhatsapp'] ?? false);
        $minta->Jalankan($pk->Uuid, $this->Pelaku()->Id, $kirim);

        return back()->with('Kilat', $kirim
            ? "Tautan persetujuan {$pk->Nomor} dibuat dan sedang dikirim lewat WhatsApp. Salin tautannya bila pelanggan belum menerima."
            : "Tautan persetujuan {$pk->Nomor} dibuat. Salin dan kirim ke pelanggan.");
    }

    /** Pelanggan memutuskan langsung di bengkel atau lewat telepon: staf mencatat baris yang disetujui. */
    public function CatatPersetujuan(Request $permintaan, string $perintahKerja, PutuskanPersetujuanServis $putuskan): RedirectResponse
    {
        $pk = $this->CariPerintahKerja($perintahKerja);
        $valid = $permintaan->validate([
            'Setuju' => ['required', 'boolean'],
            'Baris' => ['present', 'array', 'max:100'],
            'Baris.*' => ['string', 'ulid'],
            'Catatan' => ['nullable', 'string', 'max:255'],
        ]);
        $setuju = (bool) $valid['Setuju'];
        $putuskan->Jalankan($pk->Uuid, $setuju, array_values(array_map('strval', (array) $valid['Baris'])), LewatPersetujuan::Staf, $this->Pelaku()->Id, null, isset($valid['Catatan']) ? (string) $valid['Catatan'] : null);

        return back()->with('Kilat', $setuju ? "Persetujuan {$pk->Nomor} dicatat." : "{$pk->Nomor} dicatat ditolak pelanggan.");
    }

    public function AturServis(Request $permintaan, string $perintahKerja, AturServisBerikutnya $atur): RedirectResponse
    {
        $pk = $this->CariPerintahKerja($perintahKerja);
        $valid = $permintaan->validate([
            'ServisBerikutnyaPada' => ['nullable', 'date_format:Y-m-d'],
            'ServisBerikutnyaKm' => ['nullable', 'integer', 'min:1', 'max:9999999'],
        ]);
        $tanggal = isset($valid['ServisBerikutnyaPada']) ? (CarbonImmutable::createFromFormat('!Y-m-d', (string) $valid['ServisBerikutnyaPada']) ?: null) : null;
        $atur->Jalankan($pk->Uuid, $tanggal, isset($valid['ServisBerikutnyaKm']) ? (int) $valid['ServisBerikutnyaKm'] : null, $this->Pelaku()->Id, $this->HariIni());

        return back()->with('Kilat', "Jadwal servis berikutnya {$pk->Nomor} disimpan.");
    }

    /** Cetak A4 (juga rapi di printer thermal lebar lewat dialog cetak peramban): perintah kerja + estimasi. */
    public function Cetak(string $perintahKerja, DetailPerintahKerja $detail, ProfilTenant $profil): Response
    {
        $pk = $this->CariPerintahKerja($perintahKerja);
        $usaha = $profil->Ambil($this->IdTenant());

        return Inertia::render('Kelola/Bengkel/PerintahKerja/Cetak', [
            'PerintahKerja' => $detail->Ambil($pk),
            'Usaha' => ['Nama' => $usaha['Nama'], 'Npwp' => $usaha['Npwp']],
        ]);
    }

    public function CariProduk(Request $permintaan, CariProdukBengkel $cari): JsonResponse
    {
        $valid = $permintaan->validate([
            'kata' => ['nullable', 'string', 'max:100'],
            'jenis' => ['required', Rule::enum(JenisBarisPerintahKerja::class)],
            'outlet' => ['nullable', 'string', 'ulid'],
        ]);
        $idOutlet = isset($valid['outlet']) ? $this->CariOutlet((string) $valid['outlet'])->Id : null;

        return response()->json(['Data' => $cari->Cari((string) ($valid['kata'] ?? ''), JenisBarisPerintahKerja::from((string) $valid['jenis']), $idOutlet)]);
    }

    public function CariKendaraan(Request $permintaan, DaftarKendaraan $daftar): JsonResponse
    {
        $valid = $permintaan->validate(['kata' => ['nullable', 'string', 'max:30'], 'pelanggan' => ['nullable', 'string', 'ulid']]);

        return response()->json(['Data' => $daftar->Cari((string) ($valid['kata'] ?? ''), isset($valid['pelanggan']) ? (string) $valid['pelanggan'] : null)]);
    }

    public function CariPelanggan(Request $permintaan, DaftarPilihanPelanggan $cari): JsonResponse
    {
        return response()->json(['Data' => $cari->Cari((string) $permintaan->query('kata', ''))]);
    }

    /**
     * @param  array<string, mixed>|null  $isian
     * @param  array<string, mixed>|null  $awal
     */
    private function RenderForm(?array $isian, ?array $awal): Response
    {
        return Inertia::render('Kelola/Bengkel/PerintahKerja/Form', [
            'Isian' => $isian,
            'Awal' => $awal,
            'OpsiOutlet' => $this->OpsiOutlet(),
            'OpsiMekanik' => $this->OpsiMekanik(),
            'HariBerlakuTautan' => TautanPersetujuanServis::HARI_BERLAKU,
        ]);
    }
}
