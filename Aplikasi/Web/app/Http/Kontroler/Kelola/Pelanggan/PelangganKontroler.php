<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pelanggan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Pelanggan\Aksi\SimpanPelanggan;
use App\Domain\Pelanggan\Aksi\UbahStatusPelanggan;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Kueri\DaftarPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
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
        ]);
    }

    public function Detail(string $pelanggan, BelanjaPelanggan $belanja): Response
    {
        $data = $this->CariPelanggan($pelanggan);

        return Inertia::render('Kelola/Pelanggan/Detail', [
            'Pelanggan' => [
                ...DaftarPelanggan::Petakan($data),
                ...($belanja->AmbilRingkasan([$data->Id])[$data->Id] ?? ['JumlahTransaksi' => 0, 'TotalBelanja' => '0.00', 'TerakhirPada' => null]),
            ],
            'Riwayat' => $belanja->AmbilRiwayat($data->Id),
            'Izin' => $this->AmbilIzin(),
        ]);
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
