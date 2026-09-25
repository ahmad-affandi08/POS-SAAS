<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Karyawan;

use App\Domain\Karyawan\Aksi\SalinJadwalMingguLalu;
use App\Domain\Karyawan\Aksi\SimpanJadwalMingguan;
use App\Domain\Karyawan\Data\DataJadwalMingguan;
use App\Domain\Karyawan\Kueri\JadwalMingguan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal kerja mingguan per outlet (F-18, EMP-02, `/kelola/karyawan/jadwal?outlet=&minggu=`): isian Senin–Minggu per
 * karyawan, simpan, dan salin minggu lalu. Lihat `karyawan.lihat`; ubah `karyawan.kelola`. Outlet di luar akses = 404.
 */
final class JadwalKerjaKontroler extends DasarKelolaKontroler
{
    public function Tampil(Request $permintaan, JadwalMingguan $jadwal, PetaUuidOutlet $peta): Response
    {
        $opsi = $peta->AmbilRingkas($this->IdOutletBoleh(), true);
        $uuid = $permintaan->query('outlet');
        $outlet = is_string($uuid) && $uuid !== '' ? $this->CariOutlet($uuid) : null;
        $idOutlet = $outlet === null ? ($opsi[0]['Id'] ?? null) : $outlet->Id;
        $senin = self::AmbilSenin($permintaan->query('minggu'), $idOutlet);

        return Inertia::render('Kelola/Karyawan/Jadwal', [
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $opsi),
            'UuidOutlet' => $outlet === null ? ($opsi[0]['Uuid'] ?? null) : $outlet->Uuid,
            'Senin' => $senin->toDateString(),
            'Jadwal' => $idOutlet === null ? ['Hari' => [], 'Baris' => []] : $jadwal->Ambil($idOutlet, $senin),
            'Izin' => ['Kelola' => app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::KaryawanKelola)],
        ]);
    }

    public function Simpan(Request $permintaan, SimpanJadwalMingguan $simpan): RedirectResponse
    {
        $valid = $permintaan->validate([
            'UuidOutlet' => ['required', 'string', 'ulid'],
            'Senin' => ['required', 'date_format:Y-m-d'],
            'Sel' => ['present', 'array', 'max:700'],
            'Sel.*.UuidKaryawan' => ['required', 'string', 'ulid'],
            'Sel.*.Tanggal' => ['required', 'date_format:Y-m-d'],
            'Sel.*.JamMulai' => ['nullable', 'string', 'size:5'],
            'Sel.*.JamSelesai' => ['nullable', 'string', 'size:5'],
        ], attributes: ['UuidOutlet' => 'outlet', 'Senin' => 'minggu']);
        $outlet = $this->CariOutlet((string) $valid['UuidOutlet']);
        $senin = self::AmbilSenin($valid['Senin'], $outlet->Id);
        /** @var list<array{UuidKaryawan: string, Tanggal: string, JamMulai?: string|null, JamSelesai?: string|null}> $sel */
        $sel = $valid['Sel'];
        $jumlah = $simpan->Jalankan(new DataJadwalMingguan(
            $outlet->Id,
            $senin,
            array_map(fn (array $s): array => [
                'UuidKaryawan' => $s['UuidKaryawan'],
                'Tanggal' => $s['Tanggal'],
                'JamMulai' => ($s['JamMulai'] ?? '') === '' ? null : $s['JamMulai'],
                'JamSelesai' => ($s['JamSelesai'] ?? '') === '' ? null : $s['JamSelesai'],
            ], $sel),
            $this->Pelaku()->Id,
        ));

        return back()->with('Kilat', $jumlah === 0 ? 'Tidak ada perubahan jadwal.' : "Jadwal {$outlet->Nama} disimpan ({$jumlah} hari berubah).");
    }

    public function Salin(Request $permintaan, SalinJadwalMingguLalu $salin): RedirectResponse
    {
        $valid = $permintaan->validate(['UuidOutlet' => ['required', 'string', 'ulid'], 'Senin' => ['required', 'date_format:Y-m-d']]);
        $outlet = $this->CariOutlet((string) $valid['UuidOutlet']);
        $jumlah = $salin->Jalankan($outlet->Id, self::AmbilSenin($valid['Senin'], $outlet->Id), $this->Pelaku()->Id);

        return back()->with('Kilat', $jumlah === 0 ? 'Tidak ada jadwal minggu lalu yang bisa disalin.' : "{$jumlah} jadwal disalin dari minggu lalu.");
    }

    /** Senin dari tanggal `YYYY-MM-DD` (atau minggu ini menurut tanggal bisnis outlet). */
    private static function AmbilSenin(mixed $tanggal, ?int $idOutlet): CarbonImmutable
    {
        $dasar = is_string($tanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) === 1
            ? CarbonImmutable::createFromFormat('!Y-m-d', $tanggal, 'UTC')
            : null;
        $dasar = $dasar instanceof CarbonImmutable ? $dasar : CarbonImmutable::parse(app(TanggalBisnisOutlet::class)->Hitung($idOutlet)->toDateString(), 'UTC');

        return $dasar->startOfWeek(CarbonImmutable::MONDAY);
    }
}
