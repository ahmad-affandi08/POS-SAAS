<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pelanggan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Aksi\SesuaikanDeposit;
use App\Domain\Pelanggan\Aksi\TarikDeposit;
use App\Domain\Pelanggan\Model\MutasiDeposit;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Penjualan\Aksi\BatalkanIsiDeposit;
use App\Domain\Penjualan\Kueri\DaftarIsiDeposit;
use App\Domain\Penjualan\Model\IsiDeposit;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * F-16d bagian 1: deposit pelanggan di back-office. Lihat daftar isi deposit: `pelanggan.lihat`; tarik/sesuaikan
 * deposit & batal isi deposit: `pelanggan.deposit.kelola`.
 */
final class DepositPelangganKontroler extends DasarKelolaKontroler
{
    public function Tarik(Request $permintaan, string $pelanggan, TarikDeposit $tarik, TanggalBisnisOutlet $tanggal): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Jumlah' => ['required', 'numeric', 'gt:0'],
            'UuidAkun' => ['required', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'max:255'],
        ], attributes: ['Jumlah' => 'jumlah', 'UuidAkun' => 'akun kas/bank', 'Alasan' => 'alasan']);
        $data = $this->CariPelanggan($pelanggan);
        $mutasi = $tarik->Jalankan($data, Uang::Dari((string) $valid['Jumlah']), (string) $valid['UuidAkun'], (string) $valid['Alasan'], $this->Pelaku()->Id, $tanggal->Hitung(null));

        return back()->with('Kilat', "Deposit {$data->Nama} ditarik. Saldo sekarang ".Uang::Dari($mutasi->SaldoSetelah)->FormatRupiah().'.');
    }

    public function Sesuaikan(Request $permintaan, string $pelanggan, SesuaikanDeposit $sesuaikan, TanggalBisnisOutlet $tanggal): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Jumlah' => ['required', 'numeric', 'not_in:0'],
            'Alasan' => ['required', 'string', 'max:255'],
        ], attributes: ['Jumlah' => 'jumlah', 'Alasan' => 'alasan']);
        $data = $this->CariPelanggan($pelanggan);
        $mutasi = $sesuaikan->Jalankan($data, Uang::Dari((string) $valid['Jumlah']), (string) $valid['Alasan'], $this->Pelaku()->Id, $tanggal->Hitung(null));

        return back()->with('Kilat', "Deposit {$data->Nama} disesuaikan. Saldo sekarang ".Uang::Dari($mutasi->SaldoSetelah)->FormatRupiah().'.');
    }

    public function DaftarIsi(Request $permintaan, DaftarIsiDeposit $daftar, AksesPengguna $akses): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarIsiDeposit::KOLOM_URUT, DaftarIsiDeposit::URUT_BAWAAN, DaftarIsiDeposit::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Pelanggan/IsiDeposit', 'IsiDeposit', fn (): array => $daftar->AmbilTabel($tabel), fn (): array => [
            'Izin' => ['KelolaDeposit' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PelangganDepositKelola)],
        ]);
    }

    /** Tautan sumber jurnal isi deposit: ke daftar isi deposit yang disaring nomornya. */
    public function TampilkanIsi(string $isiDeposit): RedirectResponse
    {
        $isi = IsiDeposit::query()->where('Uuid', $isiDeposit)->firstOrFail();

        return redirect()->route('kelola.pelanggan.isi-deposit.daftar', ['cari' => $isi->Nomor]);
    }

    /** Tautan sumber jurnal penarikan/penyesuaian deposit: ke detail pelanggannya. */
    public function TampilkanMutasi(string $mutasiDeposit): RedirectResponse
    {
        $mutasi = MutasiDeposit::query()->where('Uuid', $mutasiDeposit)->firstOrFail();
        $pelanggan = Pelanggan::query()->whereKey($mutasi->IdPelanggan)->firstOrFail();

        return redirect()->route('kelola.pelanggan.detail', ['pelanggan' => $pelanggan->Uuid]);
    }

    public function BatalIsi(Request $permintaan, string $isiDeposit, BatalkanIsiDeposit $batalkan): RedirectResponse
    {
        $valid = $permintaan->validate(['Alasan' => ['required', 'string', 'max:255']], attributes: ['Alasan' => 'alasan']);
        $isi = $batalkan->Jalankan(IsiDeposit::query()->where('Uuid', $isiDeposit)->firstOrFail(), (string) $valid['Alasan'], $this->Pelaku()->Id);

        return back()->with('Kilat', "Isi deposit {$isi->Nomor} dibatalkan. Kembalikan uangnya ke pelanggan dari kas/bank.");
    }

    private function CariPelanggan(string $uuid): Pelanggan
    {
        return Pelanggan::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
