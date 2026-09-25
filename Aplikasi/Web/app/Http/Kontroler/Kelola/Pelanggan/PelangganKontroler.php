<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pelanggan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Aksi\AturTierPelanggan;
use App\Domain\Pelanggan\Aksi\SesuaikanPoin;
use App\Domain\Pelanggan\Aksi\SimpanPelanggan;
use App\Domain\Pelanggan\Aksi\UbahStatusPelanggan;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Kueri\DaftarPelanggan;
use App\Domain\Pelanggan\Kueri\DaftarTierPelanggan;
use App\Domain\Pelanggan\Kueri\PengaturanLoyaltiTenant;
use App\Domain\Pelanggan\Kueri\RiwayatPoin;
use App\Domain\Pelanggan\Layanan\BukuPoin;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use App\Domain\Penjualan\Kueri\BelanjaPelanggan;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Pelanggan\SimpanPelangganPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pelanggan back-office (F-16a CRM-01, `/kelola/pelanggan`): daftar (`TabelData`), tambah/ubah, arsipkan/pulihkan
 * (izin `pelanggan.kelola`), dan detail dengan riwayat belanja (izin `pelanggan.lihat`). Izin rute dijaga
 * `WajibIzinTenant`; prop `Izin` hanya untuk tampilan.
 */
final class PelangganKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarPelanggan $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPelanggan::KOLOM_URUT, DaftarPelanggan::URUT_BAWAAN, DaftarPelanggan::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Pelanggan/Daftar', 'Pelanggan', fn (): array => $daftar->AmbilTabel($tabel), fn (): array => [
            'Izin' => $this->AmbilIzin(),
            'OpsiTag' => $daftar->AmbilSemuaTag(),
            'OpsiTier' => app(DaftarTierPelanggan::class)->AmbilOpsi(),
        ]);
    }

    public function Detail(string $pelanggan, BelanjaPelanggan $belanja, DaftarTierPelanggan $tier, BukuPoin $buku, RiwayatPoin $riwayatPoin, PengaturanLoyaltiTenant $loyalti): Response
    {
        $data = $this->CariPelanggan($pelanggan);
        $tierPelanggan = $data->IdTier === null ? null : ($tier->AmbilPeta([$data->IdTier])[$data->IdTier] ?? null);

        return Inertia::render('Kelola/Pelanggan/Detail', [
            'Pelanggan' => [
                ...DaftarPelanggan::Petakan($data, $tierPelanggan, $buku->AmbilSaldo($data->Id)),
                ...($belanja->AmbilRingkasan([$data->Id])[$data->Id] ?? ['JumlahTransaksi' => 0, 'TotalBelanja' => '0.00', 'TerakhirPada' => null]),
            ],
            'Riwayat' => $belanja->AmbilRiwayat($data->Id),
            'RiwayatPoin' => $riwayatPoin->Ambil($data->Id),
            'OpsiTier' => $tier->AmbilOpsi($tierPelanggan['Kode'] ?? null),
            'LoyaltiBerlaku' => $loyalti->Ambil()->CekBerlaku(),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    /** F-16b: atur tier manual & kunci tier. */
    public function AturTier(Request $permintaan, string $pelanggan, AturTierPelanggan $atur): RedirectResponse
    {
        $valid = $permintaan->validate([
            'UuidTier' => ['nullable', 'string', 'ulid'],
            'TierTetap' => ['required', 'boolean'],
        ], attributes: ['UuidTier' => 'tier']);
        $tier = is_string($valid['UuidTier'] ?? null) ? TierPelanggan::query()->where('Uuid', $valid['UuidTier'])->first() : null;

        if (is_string($valid['UuidTier'] ?? null) && $tier === null) {
            return back()->withErrors(['UuidTier' => 'Tier tidak ditemukan.']);
        }

        $hasil = $atur->Jalankan($this->CariPelanggan($pelanggan), $tier, $permintaan->boolean('TierTetap'), $this->Pelaku()->Id);

        return back()->with('Kilat', "Tier {$hasil->Nama} disimpan.");
    }

    /** F-16b: penyesuaian poin manual dengan alasan. */
    public function SesuaikanPoin(Request $permintaan, string $pelanggan, SesuaikanPoin $sesuaikan, TanggalBisnisOutlet $tanggal): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Poin' => ['required', 'integer', 'between:-100000,100000'],
            'Alasan' => ['required', 'string', 'max:255'],
        ], attributes: ['Poin' => 'poin', 'Alasan' => 'alasan']);
        $data = $this->CariPelanggan($pelanggan);
        $saldo = $sesuaikan->Jalankan($data, (int) $valid['Poin'], (string) $valid['Alasan'], $this->Pelaku()->Id, $tanggal->Hitung(null));

        return back()->with('Kilat', "Poin {$data->Nama} sekarang {$saldo}.");
    }

    public function Simpan(SimpanPelangganPermintaan $permintaan, SimpanPelanggan $simpan): RedirectResponse
    {
        $pelanggan = $simpan->Jalankan($permintaan->AmbilData($this->Pelaku()->Id));

        return back()->with('Kilat', "Pelanggan {$pelanggan->Nama} ditambahkan.");
    }

    public function Perbarui(SimpanPelangganPermintaan $permintaan, string $pelanggan, SimpanPelanggan $simpan): RedirectResponse
    {
        $hasil = $simpan->Jalankan($permintaan->AmbilData($this->Pelaku()->Id), $this->CariPelanggan($pelanggan));

        return back()->with('Kilat', "Pelanggan {$hasil->Nama} disimpan.");
    }

    public function Arsipkan(string $pelanggan, UbahStatusPelanggan $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariPelanggan($pelanggan), StatusPelanggan::Diarsipkan, $this->Pelaku()->Id);

        return back()->with('Kilat', "Pelanggan {$hasil->Nama} diarsipkan; tidak muncul lagi di pencarian kasir.");
    }

    public function Pulihkan(string $pelanggan, UbahStatusPelanggan $ubah): RedirectResponse
    {
        $hasil = $ubah->Jalankan($this->CariPelanggan($pelanggan), StatusPelanggan::Aktif, $this->Pelaku()->Id);

        return back()->with('Kilat', "Pelanggan {$hasil->Nama} dipulihkan.");
    }

    private function CariPelanggan(string $uuid): Pelanggan
    {
        return Pelanggan::query()->where('Uuid', $uuid)->firstOrFail();
    }

    /**
     * @return array{Kelola: bool, LihatPenjualan: bool}
     */
    private function AmbilIzin(): array
    {
        $akses = app(AksesPengguna::class);

        return [
            'Kelola' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganKelola),
            'LihatPenjualan' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::LaporanPenjualanLihat),
        ];
    }
}
