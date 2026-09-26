<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pelanggan;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Aksi\BatalkanPemakaianSesi;
use App\Domain\Pelanggan\Aksi\TutupSisaSesi;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Kueri\DaftarSaldoSesi;
use App\Domain\Pelanggan\Model\MutasiSesi;
use App\Domain\Pelanggan\Model\PemakaianSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F-16d bagian 2: saldo paket sesi pelanggan di back-office. Lihat: `pelanggan.lihat`; kembalikan/hanguskan sisa sesi &
 * batalkan pemakaian: `pelanggan.sesi.kelola`.
 */
final class SesiPelangganKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarSaldoSesi $daftar, AksesPengguna $akses): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarSaldoSesi::KOLOM_URUT, DaftarSaldoSesi::URUT_BAWAAN, DaftarSaldoSesi::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Pelanggan/SaldoSesi/Daftar', 'SaldoSesi', fn (): array => $daftar->AmbilTabel($tabel), fn (): array => [
            'Izin' => ['KelolaSesi' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganSesiKelola)],
        ]);
    }

    public function Tampilkan(string $saldoSesi, DaftarSaldoSesi $daftar, AksesPengguna $akses, DaftarAkunPilihan $akun): Response
    {
        $kelola = $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganSesiKelola);

        return Inertia::render('Kelola/Pelanggan/SaldoSesi/Detail', [
            'Saldo' => $daftar->AmbilDetail($this->CariSaldo($saldoSesi)),
            'AkunKasBank' => $kelola ? $akun->AmbilKasBank() : [],
            'Izin' => ['KelolaSesi' => $kelola],
        ]);
    }

    public function Tutup(Request $permintaan, string $saldoSesi, TutupSisaSesi $tutup, TanggalBisnisOutlet $tanggal): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Jenis' => ['required', 'string', 'in:Refund,Hangus'],
            'UuidAkun' => ['nullable', 'required_if:Jenis,Refund', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'max:255'],
        ], attributes: ['Jenis' => 'tindakan', 'UuidAkun' => 'akun kas/bank', 'Alasan' => 'alasan']);
        $saldo = $this->CariSaldo($saldoSesi);
        $jenis = JenisMutasiSesi::from((string) $valid['Jenis']);
        $tutup->Jalankan($saldo, $jenis, isset($valid['UuidAkun']) ? (string) $valid['UuidAkun'] : null, (string) $valid['Alasan'], $this->Pelaku()->Id, $tanggal->Hitung(null));

        return back()->with('Kilat', $jenis === JenisMutasiSesi::Refund
            ? "Sisa paket {$saldo->NamaPaket} dikembalikan ke pelanggan."
            : "Sisa paket {$saldo->NamaPaket} dihanguskan.");
    }

    public function BatalPemakaian(Request $permintaan, string $pemakaianSesi, BatalkanPemakaianSesi $batalkan, TanggalBisnisOutlet $tanggal): RedirectResponse
    {
        $valid = $permintaan->validate(['Alasan' => ['required', 'string', 'max:255']], attributes: ['Alasan' => 'alasan']);
        $batalkan->Jalankan(PemakaianSesi::query()->where('Uuid', $pemakaianSesi)->firstOrFail(), (string) $valid['Alasan'], $this->Pelaku()->Id, $tanggal->Hitung(null));

        return back()->with('Kilat', 'Pemakaian sesi dibatalkan, sesinya kembali ke pelanggan.');
    }

    /** Tautan sumber jurnal pemakaian sesi: ke detail saldo sesinya. */
    public function TampilkanPemakaian(string $pemakaianSesi): RedirectResponse
    {
        $pemakaian = PemakaianSesi::query()->where('Uuid', $pemakaianSesi)->firstOrFail();

        return redirect()->route('kelola.pelanggan.saldo-sesi.tampil', ['saldoSesi' => $pemakaian->UuidSaldoSesi]);
    }

    /** Tautan sumber jurnal pengembalian/hangus sisa sesi: ke detail saldo sesinya. */
    public function TampilkanMutasi(string $mutasiSesi): RedirectResponse
    {
        $mutasi = MutasiSesi::query()->where('Uuid', $mutasiSesi)->firstOrFail();
        $saldo = SaldoSesi::query()->whereKey($mutasi->IdSaldoSesi)->firstOrFail();

        return redirect()->route('kelola.pelanggan.saldo-sesi.tampil', ['saldoSesi' => $saldo->Uuid]);
    }

    private function CariSaldo(string $uuid): SaldoSesi
    {
        return SaldoSesi::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
