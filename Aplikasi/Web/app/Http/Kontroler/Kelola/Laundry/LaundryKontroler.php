<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Laundry;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pemenuhan\Aksi\SimpanPengaturanLaundry;
use App\Domain\Pemenuhan\Aksi\UbahStatusLaundry;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Kueri\DaftarLaundry;
use App\Domain\Pemenuhan\Kueri\PengaturanLaundryTenant;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Laundry di back-office (§9.9, izin `laundry.kelola`, dibatasi outlet akses): daftar tiket (TabelData, termasuk
 * laporan cucian terlambat/belum diambil), ubah status proses, dan pengaturan laundry (hanya pengguna tanpa batas
 * outlet). Tiket dibuat dari aplikasi kasir bersama penjualannya.
 */
final class LaundryKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarLaundry $daftar, ProfilTenant $profil, PetaUuidOutlet $outlet, PengaturanLaundryTenant $pengaturan): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarLaundry::KOLOM_URUT, DaftarLaundry::URUT_BAWAAN, DaftarLaundry::KOLOM_SARING);
        $zona = $profil->Ambil($this->IdTenant())['ZonaWaktu'];

        return ResponsTabel::Kirim($permintaan, 'Kelola/Laundry/Daftar', 'Tiket', fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh(), $zona), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusLaundry $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusLaundry::cases()),
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh(), hanyaAktif: true)),
            'Pengaturan' => $pengaturan->AmbilLarik(),
            'ZonaWaktu' => $zona,
            'Izin' => ['Pengaturan' => $this->IdOutletBoleh() === null],
        ]);
    }

    public function UbahStatus(Request $permintaan, string $tiket, DaftarLaundry $daftar, UbahStatusLaundry $ubah): RedirectResponse
    {
        $t = $daftar->Cari($tiket, $this->IdOutletBoleh());
        abort_unless($t instanceof TiketLaundry, 404);
        $valid = $permintaan->validate(['Status' => ['required', Rule::enum(StatusLaundry::class)]]);
        $status = StatusLaundry::from((string) $valid['Status']);
        $ubah->Jalankan($t, $status, $this->Pelaku()->Id);

        return back()->with('Kilat', "Cucian {$t->Nomor}: {$status->AmbilLabel()}.");
    }

    public function SimpanPengaturan(Request $permintaan, SimpanPengaturanLaundry $simpan): RedirectResponse
    {
        abort_unless($this->IdOutletBoleh() === null, 403);
        $valid = $permintaan->validate([
            'Aktif' => ['required', 'boolean'],
            'JamReguler' => ['required', 'integer'],
            'JamExpress' => ['required', 'integer'],
            'Parfum' => ['present', 'array', 'max:50'],
            'Parfum.*' => ['string', 'max:50'],
            'NotifikasiSiap' => ['required', 'boolean'],
            'HariBelumDiambil' => ['required', 'integer'],
        ]);
        $simpan->Jalankan([
            'Aktif' => (bool) $valid['Aktif'],
            'JamReguler' => (int) $valid['JamReguler'],
            'JamExpress' => (int) $valid['JamExpress'],
            'Parfum' => array_values(array_map('strval', (array) $valid['Parfum'])),
            'NotifikasiSiap' => (bool) $valid['NotifikasiSiap'],
            'HariBelumDiambil' => (int) $valid['HariBelumDiambil'],
        ], $this->Pelaku()->Id);

        return back()->with('Kilat', 'Pengaturan laundry disimpan.');
    }
}
