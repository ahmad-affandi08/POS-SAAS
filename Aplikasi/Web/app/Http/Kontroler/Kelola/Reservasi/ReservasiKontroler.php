<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Reservasi;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\LayananReservasi;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pemenuhan\Aksi\BuatReservasi;
use App\Domain\Pemenuhan\Aksi\JadwalkanUlangReservasi;
use App\Domain\Pemenuhan\Aksi\SimpanPengaturanReservasi;
use App\Domain\Pemenuhan\Aksi\UbahStatusReservasi;
use App\Domain\Pemenuhan\Data\DataReservasi;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Enum\SumberReservasi;
use App\Domain\Pemenuhan\Kueri\DaftarReservasi;
use App\Domain\Pemenuhan\Kueri\PengaturanReservasiTenant;
use App\Domain\Pemenuhan\Kueri\SlotReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * F-07 mode service (POS-04, SLS-07): reservasi layanan di back-office (izin `reservasi.kelola`, dibatasi outlet akses):
 * daftar (TabelData), slot kosong (JSON), buat, ubah status, pindah jadwal, dan pengaturan reservasi. Parameter rute
 * berupa string karena konteks tenant ditetapkan setelah binding.
 */
final class ReservasiKontroler extends DasarKelolaKontroler
{
    private const POLA_TANGGAL = 'date_format:Y-m-d';

    private const POLA_JAM = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    public function Daftar(Request $permintaan, DaftarReservasi $daftar, ProfilTenant $profil, PetaUuidOutlet $outlet, JadwalStafReservasi $staf, LayananReservasi $layanan, PengaturanReservasiTenant $pengaturan, TanggalBisnisOutlet $tanggal): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarReservasi::KOLOM_URUT, DaftarReservasi::URUT_BAWAAN, DaftarReservasi::KOLOM_SARING);
        $zona = $profil->Ambil($this->IdTenant())['ZonaWaktu'];

        return ResponsTabel::Kirim($permintaan, 'Kelola/Reservasi/Daftar', 'Reservasi', fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh(), $zona), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusReservasi $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusReservasi::cases()),
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh(), hanyaAktif: true)),
            'OpsiStaf' => $staf->AmbilOpsi(),
            'OpsiLayanan' => array_map(fn (array $l): array => ['Uuid' => $l['Uuid'], 'Nama' => $l['Nama'], 'DurasiMenit' => $l['DurasiMenit'], 'Harga' => $l['Harga']], $layanan->Ambil()),
            'Pengaturan' => $pengaturan->AmbilLarik(),
            'HariIni' => $tanggal->Hitung(null)->toDateString(),
            'TautanPublik' => url('/'.app(ProfilTenant::class)->AmbilSlug($this->IdTenant()).'/reservasi'),
            'Izin' => ['Pengaturan' => $this->CekPengaturan()],
        ]);
    }

    public function Slot(Request $permintaan, SlotReservasi $slot, LayananReservasi $layanan, JadwalStafReservasi $staf, PengaturanReservasiTenant $pengaturan): JsonResponse
    {
        $valid = $permintaan->validate([
            'Outlet' => ['required', 'string', 'max:26'],
            'Layanan' => ['required', 'string', 'max:26'],
            'Tanggal' => ['required', self::POLA_TANGGAL],
            'Staf' => ['nullable', 'string', 'max:26'],
        ]);
        $idOutlet = $this->CariIdOutlet((string) $valid['Outlet']);
        $l = $layanan->Cari((string) $valid['Layanan']);
        abort_if($l === null, 404);
        $idStaf = isset($valid['Staf']) && $valid['Staf'] !== '' ? ($staf->CariStaf((string) $valid['Staf'])['Id'] ?? 0) : null;

        return response()->json(['Slot' => array_map(
            fn (array $s): array => ['Jam' => $s['Jam'], 'Staf' => array_map(fn (array $k): array => ['Uuid' => $k['Uuid'], 'Nama' => $k['Nama']], $s['Staf'])],
            $slot->Hitung($idOutlet, (string) $valid['Tanggal'], $l['DurasiMenit'], $idStaf, $pengaturan->Ambil(), CarbonImmutable::now()->subMinutes(5)),
        )]);
    }

    public function Simpan(Request $permintaan, BuatReservasi $buat): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Outlet' => ['required', 'string', 'max:26'],
            'UuidLayanan' => ['required', 'string', 'max:26'],
            'Tanggal' => ['required', self::POLA_TANGGAL],
            'Jam' => ['required', self::POLA_JAM],
            'UuidStaf' => ['nullable', 'string', 'max:26'],
            'NamaPelanggan' => ['required', 'string', 'max:100'],
            'NoHp' => ['required', 'string', 'max:20'],
            'Catatan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['UuidLayanan' => 'layanan', 'NamaPelanggan' => 'nama pelanggan', 'NoHp' => 'nomor HP']);
        $r = $buat->Jalankan($this->IdTenant(), new DataReservasi(
            idOutlet: $this->CariIdOutlet((string) $valid['Outlet']),
            uuidLayanan: (string) $valid['UuidLayanan'],
            tanggal: (string) $valid['Tanggal'],
            jam: (string) $valid['Jam'],
            uuidStaf: isset($valid['UuidStaf']) ? (string) $valid['UuidStaf'] : null,
            namaPelanggan: (string) $valid['NamaPelanggan'],
            noHp: (string) $valid['NoHp'],
            catatan: isset($valid['Catatan']) ? (string) $valid['Catatan'] : null,
            sumber: SumberReservasi::BackOffice,
            idPengguna: $this->Pelaku()->Id,
        ));

        return back()->with('Kilat', "Reservasi {$r->Nomor} dicatat.");
    }

    public function UbahStatus(Request $permintaan, string $reservasi, DaftarReservasi $daftar, UbahStatusReservasi $ubah): RedirectResponse
    {
        $r = $this->Cari($daftar, $reservasi);
        $valid = $permintaan->validate([
            'Status' => ['required', Rule::enum(StatusReservasi::class)],
            'Alasan' => ['nullable', 'string', 'max:255'],
        ]);
        $status = StatusReservasi::from((string) $valid['Status']);
        $ubah->Jalankan($r, $status, $this->Pelaku()->Id, isset($valid['Alasan']) ? (string) $valid['Alasan'] : null);

        return back()->with('Kilat', "Reservasi {$r->Nomor}: {$status->AmbilLabel()}.");
    }

    public function JadwalUlang(Request $permintaan, string $reservasi, DaftarReservasi $daftar, JadwalkanUlangReservasi $jadwal): RedirectResponse
    {
        $r = $this->Cari($daftar, $reservasi);
        $valid = $permintaan->validate([
            'Tanggal' => ['required', self::POLA_TANGGAL],
            'Jam' => ['required', self::POLA_JAM],
            'UuidStaf' => ['nullable', 'string', 'max:26'],
        ]);
        $jadwal->Jalankan($this->IdTenant(), $r, (string) $valid['Tanggal'], (string) $valid['Jam'], isset($valid['UuidStaf']) ? (string) $valid['UuidStaf'] : null, $this->Pelaku()->Id);

        return back()->with('Kilat', "Jadwal reservasi {$r->Nomor} dipindah ke {$valid['Tanggal']} {$valid['Jam']}.");
    }

    public function SimpanPengaturan(Request $permintaan, SimpanPengaturanReservasi $simpan): RedirectResponse
    {
        abort_unless($this->CekPengaturan(), 403);
        $valid = $permintaan->validate([
            'OnlineAktif' => ['required', 'boolean'],
            'KonfirmasiOtomatis' => ['required', 'boolean'],
            'IntervalSlotMenit' => ['required', 'integer'],
            'JedaMenit' => ['required', 'integer'],
            'BatasHariKeDepan' => ['required', 'integer'],
            'MinimalMenitSebelum' => ['required', 'integer'],
            'PengingatAktif' => ['required', 'boolean'],
        ]);
        $simpan->Jalankan([
            'OnlineAktif' => (bool) $valid['OnlineAktif'],
            'KonfirmasiOtomatis' => (bool) $valid['KonfirmasiOtomatis'],
            'IntervalSlotMenit' => (int) $valid['IntervalSlotMenit'],
            'JedaMenit' => (int) $valid['JedaMenit'],
            'BatasHariKeDepan' => (int) $valid['BatasHariKeDepan'],
            'MinimalMenitSebelum' => (int) $valid['MinimalMenitSebelum'],
            'PengingatAktif' => (bool) $valid['PengingatAktif'],
        ], $this->Pelaku()->Id);

        return back()->with('Kilat', 'Pengaturan reservasi disimpan.');
    }

    /** Pengaturan reservasi berlaku untuk semua outlet: hanya pengguna tanpa batas outlet. */
    private function CekPengaturan(): bool
    {
        return $this->IdOutletBoleh() === null;
    }

    private function Cari(DaftarReservasi $daftar, string $uuid): Reservasi
    {
        $r = $daftar->Cari($uuid, $this->IdOutletBoleh());
        abort_unless($r instanceof Reservasi, 404);

        return $r;
    }

    private function CariIdOutlet(string $uuid): int
    {
        $id = app(PetaUuidOutlet::class)->AmbilIdDariUuid([$uuid])[$uuid] ?? null;
        $boleh = $this->IdOutletBoleh();
        abort_if($id === null || ($boleh !== null && ! in_array($id, $boleh, true)), 404);

        return $id;
    }
}
