<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pelanggan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Pelanggan\Aksi\SimpanPengaturanLoyalti;
use App\Domain\Pelanggan\Aksi\SimpanTierPelanggan;
use App\Domain\Pelanggan\Aksi\UbahStatusTierPelanggan;
use App\Domain\Pelanggan\Data\DataTierPelanggan;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Kueri\DaftarTierPelanggan;
use App\Domain\Pelanggan\Kueri\PengaturanLoyaltiTenant;
use App\Domain\Pelanggan\Model\TierPelanggan;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loyalti back-office (F-16b): tier pelanggan (`/kelola/pelanggan/tier`) dan pengaturan poin
 * (`/kelola/pelanggan/loyalti`). Lihat: `pelanggan.lihat`; ubah: `pelanggan.kelola`. Prop `FiturAktif` = paket punya
 * `pelanggan.loyalti` (tanpa fitur, halaman tampil dengan ajakan naik paket dan perolehan poin tidak berjalan).
 */
final class LoyaltiKontroler extends DasarKelolaKontroler
{
    public function Tier(DaftarTierPelanggan $daftar, PengaturanLoyaltiTenant $loyalti): Response
    {
        return Inertia::render('Kelola/Pelanggan/Tier', [
            'Tier' => $daftar->AmbilSemua(),
            'FiturAktif' => $loyalti->Ambil()->fiturAktif,
            'Izin' => ['Kelola' => $this->CekKelola()],
        ]);
    }

    /** Halaman penuh "Tambah tier" (pola sama dengan Tambah produk). */
    public function BuatTier(PengaturanLoyaltiTenant $loyalti): Response
    {
        return Inertia::render('Kelola/Pelanggan/BuatTier', [
            'FiturAktif' => $loyalti->Ambil()->fiturAktif,
        ]);
    }

    /** Setelah ditambah, kembali ke daftar tier (bukan ke halaman buat). */
    public function SimpanTier(Request $permintaan, SimpanTierPelanggan $simpan): RedirectResponse
    {
        $tier = $simpan->Jalankan($this->AmbilDataTier($permintaan, true));

        return redirect()->route('kelola.pelanggan.tier.daftar')->with('Kilat', "Tier {$tier->Nama} ditambahkan.");
    }

    public function PerbaruiTier(Request $permintaan, string $tier, SimpanTierPelanggan $simpan): RedirectResponse
    {
        $hasil = $simpan->Jalankan($this->AmbilDataTier($permintaan, false), $this->CariTier($tier));

        return back()->with('Kilat', "Tier {$hasil->Nama} disimpan.");
    }

    public function ArsipkanTier(string $tier, UbahStatusTierPelanggan $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariTier($tier), StatusPelanggan::Diarsipkan, $this->Pelaku()->Id);

        return back()->with('Kilat', "Tier {$hasil->Nama} diarsipkan.");
    }

    public function PulihkanTier(string $tier, UbahStatusTierPelanggan $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariTier($tier), StatusPelanggan::Aktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "Tier {$hasil->Nama} dipulihkan.");
    }

    public function Pengaturan(PengaturanLoyaltiTenant $loyalti): Response
    {
        $p = $loyalti->Ambil();

        return Inertia::render('Kelola/Pelanggan/PengaturanLoyalti', [
            'Pengaturan' => [
                'Aktif' => $p->aktif,
                'BelanjaPerPoin' => (string) $p->belanjaPerPoin->toScale(2),
                'MasaBerlakuBulan' => $p->masaBerlakuBulan,
                'BulanEvaluasiTier' => $p->bulanEvaluasiTier,
                'NilaiTukarPoin' => (string) $p->nilaiTukarPoin->toScale(2),
                'MinimalTukarPoin' => $p->minimalTukarPoin,
            ],
            'FiturAktif' => $p->fiturAktif,
            'Izin' => ['Kelola' => $this->CekKelola()],
        ]);
    }

    public function SimpanPengaturan(Request $permintaan, SimpanPengaturanLoyalti $simpan): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Aktif' => ['required', 'boolean'],
            'BelanjaPerPoin' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'MasaBerlakuBulan' => ['required', 'integer', 'between:1,60'],
            'BulanEvaluasiTier' => ['required', 'integer', 'between:1,24'],
            'NilaiTukarPoin' => ['nullable', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'MinimalTukarPoin' => ['nullable', 'integer', 'between:1,100000'],
        ], attributes: [
            'BelanjaPerPoin' => 'belanja per poin',
            'MasaBerlakuBulan' => 'masa berlaku',
            'BulanEvaluasiTier' => 'periode evaluasi tier',
            'NilaiTukarPoin' => 'nilai tukar per poin',
            'MinimalTukarPoin' => 'minimal tukar',
        ]);
        $simpan->Jalankan(
            $permintaan->boolean('Aktif'),
            Uang::Dari((string) $valid['BelanjaPerPoin']),
            (int) $valid['MasaBerlakuBulan'],
            (int) $valid['BulanEvaluasiTier'],
            $this->Pelaku()->Id,
            isset($valid['NilaiTukarPoin']) ? Uang::Dari((string) $valid['NilaiTukarPoin']) : null,
            isset($valid['MinimalTukarPoin']) ? (int) $valid['MinimalTukarPoin'] : null,
        );

        return back()->with('Kilat', 'Pengaturan loyalti disimpan.');
    }

    private function AmbilDataTier(Request $permintaan, bool $baru): DataTierPelanggan
    {
        $valid = $permintaan->validate([
            'Kode' => $baru ? ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'] : ['nullable'],
            'Nama' => ['required', 'string', 'max:60'],
            'MinimalBelanja' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'PengaliPoin' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'Urutan' => ['nullable', 'integer', 'between:0,999'],
        ], [
            'Kode.regex' => 'Kode tier hanya huruf, angka, garis bawah, atau tanda hubung.',
        ], ['Kode' => 'kode', 'Nama' => 'nama', 'MinimalBelanja' => 'minimal belanja', 'PengaliPoin' => 'pengali poin']);

        return new DataTierPelanggan(
            kode: (string) ($valid['Kode'] ?? ''),
            nama: (string) $valid['Nama'],
            minimalBelanja: Uang::Dari((string) $valid['MinimalBelanja']),
            pengaliPoin: BigDecimal::of((string) $valid['PengaliPoin']),
            urutan: (int) ($valid['Urutan'] ?? 0),
            idPengguna: $this->Pelaku()->Id,
        );
    }

    private function CariTier(string $uuid): TierPelanggan
    {
        return TierPelanggan::query()->where('Uuid', $uuid)->firstOrFail();
    }

    private function CekKelola(): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganKelola);
    }
}
