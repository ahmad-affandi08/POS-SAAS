<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Karyawan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Karyawan\Aksi\SimpanAbsensiManual;
use App\Domain\Karyawan\Kueri\DaftarAbsensi;
use App\Domain\Karyawan\Kueri\DaftarKaryawan;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap absensi (F-18, EMP-03, `/kelola/karyawan/absensi`) dan swafoto masuk/keluar (disk privat), izin
 * `karyawan.lihat`. Pengguna terbatas outlet hanya melihat absensi outletnya.
 */
final class AbsensiKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarAbsensi $daftar, DaftarKaryawan $karyawan, PetaUuidOutlet $outlet, AksesPengguna $akses): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarAbsensi::KOLOM_URUT, DaftarAbsensi::URUT_BAWAAN, DaftarAbsensi::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Karyawan/Absensi', 'Absensi', fn (): array => $daftar->Ambil($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiKaryawan' => $karyawan->AmbilPilihan(),
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
            // v3.34: koreksi & tambah absensi manual hanya untuk `karyawan.kelola`.
            'BolehKoreksi' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::KaryawanKelola),
        ]);
    }

    /** v3.34: catat absensi yang terlewat (karyawan lupa absen); `Sumber` = Manual. */
    public function Tambah(Request $permintaan, SimpanAbsensiManual $simpan, PetaUuidOutlet $outlet): RedirectResponse
    {
        $valid = $permintaan->validate([...self::Aturan(), 'Karyawan' => ['required', 'ulid'], 'Outlet' => ['required', 'ulid']]);
        $idOutlet = $outlet->AmbilIdDariUuid([strtoupper((string) $valid['Outlet'])])[strtoupper((string) $valid['Outlet'])] ?? null;
        $boleh = $this->IdOutletBoleh();
        abort_if($idOutlet === null || ($boleh !== null && ! in_array($idOutlet, $boleh, true)), 404);
        $karyawan = Karyawan::query()->where('Uuid', strtoupper((string) $valid['Karyawan']))->firstOrFail();
        $simpan->Jalankan(null, $karyawan->Id, $idOutlet, ...self::AmbilJam($valid), idPengguna: $this->Pelaku()->Id);

        return back()->with('Kilat', "Absensi {$karyawan->Nama} dicatat.");
    }

    /** v3.34: koreksi jam masuk/keluar absensi (dari kasir atau manual). */
    public function Koreksi(Request $permintaan, string $absensi, SimpanAbsensiManual $simpan): RedirectResponse
    {
        $a = Absensi::query()->where('Uuid', strtoupper($absensi))->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($a->IdOutlet, $boleh, true), 404);
        $valid = $permintaan->validate(self::Aturan());
        $simpan->Jalankan($a, $a->IdKaryawan, $a->IdOutlet, ...self::AmbilJam($valid), idPengguna: $this->Pelaku()->Id);

        return back()->with('Kilat', 'Absensi dikoreksi.');
    }

    /** @return array<string, list<string>> */
    private static function Aturan(): array
    {
        return [
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'JamMasuk' => ['required', 'date_format:H:i'],
            'JamKeluar' => ['nullable', 'date_format:H:i'],
            'KeluarHariBerikutnya' => ['sometimes', 'boolean'],
            'Alasan' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $valid
     * @return array{tanggal: string, jamMasuk: string, jamKeluar: string|null, keluarHariBerikutnya: bool, alasan: string}
     */
    private static function AmbilJam(array $valid): array
    {
        return [
            'tanggal' => (string) $valid['Tanggal'],
            'jamMasuk' => (string) $valid['JamMasuk'],
            'jamKeluar' => is_string($valid['JamKeluar'] ?? null) ? $valid['JamKeluar'] : null,
            'keluarHariBerikutnya' => (bool) ($valid['KeluarHariBerikutnya'] ?? false),
            'alasan' => (string) $valid['Alasan'],
        ];
    }

    public function Swafoto(string $absensi, string $jenis, PenyimpanSwafoto $penyimpan): StreamedResponse
    {
        $a = Absensi::query()->where('Uuid', $absensi)->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($a->IdOutlet, $boleh, true), 404);

        return $penyimpan->Unduh($jenis === 'masuk' ? $a->PathSwafotoMasuk : $a->PathSwafotoKeluar);
    }
}
