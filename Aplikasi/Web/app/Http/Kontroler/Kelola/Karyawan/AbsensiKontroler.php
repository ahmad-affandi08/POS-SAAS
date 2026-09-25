<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Karyawan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Karyawan\Kueri\DaftarAbsensi;
use App\Domain\Karyawan\Kueri\DaftarKaryawan;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap absensi (F-18, EMP-03, `/kelola/karyawan/absensi`) dan swafoto masuk/keluar (disk privat), izin
 * `karyawan.lihat`. Pengguna terbatas outlet hanya melihat absensi outletnya.
 */
final class AbsensiKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarAbsensi $daftar, DaftarKaryawan $karyawan, PetaUuidOutlet $outlet): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarAbsensi::KOLOM_URUT, DaftarAbsensi::URUT_BAWAAN, DaftarAbsensi::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Karyawan/Absensi', 'Absensi', fn (): array => $daftar->Ambil($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiKaryawan' => $karyawan->AmbilPilihan(),
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
        ]);
    }

    public function Swafoto(string $absensi, string $jenis, PenyimpanSwafoto $penyimpan): StreamedResponse
    {
        $a = Absensi::query()->where('Uuid', $absensi)->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($a->IdOutlet, $boleh, true), 404);

        return $penyimpan->Unduh($jenis === 'masuk' ? $a->PathSwafotoMasuk : $a->PathSwafotoKeluar);
    }
}
