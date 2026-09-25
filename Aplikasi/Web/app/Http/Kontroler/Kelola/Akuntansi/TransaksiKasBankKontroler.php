<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\BalikkanTransaksiKasBank;
use App\Domain\Akuntansi\Aksi\SimpanTransaksiKasBank;
use App\Domain\Akuntansi\Enum\JenisTransaksiKasBank;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Kueri\DaftarTransaksiKasBank;
use App\Domain\Akuntansi\Kueri\DetailTransaksiKasBank;
use App\Domain\Akuntansi\Kueri\SaldoAkunKasBank;
use App\Domain\Akuntansi\Layanan\PenyimpanLampiranKasBank;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Http\Permintaan\Kelola\Akuntansi\BalikkanTransaksiKasBankPermintaan;
use App\Http\Permintaan\Kelola\Akuntansi\SimpanTransaksiKasBankPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transaksi kas & bank (F-13a, FIN-03): lihat `laporan.keuangan.lihat`, simpan/balik `akuntansi.kelola`. Pelaku
 * berbatas outlet wajib memilih outlet aksesnya dan hanya melihat transaksi di outlet itu; transaksi di luar akses
 * atau tenant lain = 404.
 */
final class TransaksiKasBankKontroler extends DasarAkuntansiKontroler
{
    public function Daftar(Request $permintaan, DaftarTransaksiKasBank $daftar, SaldoAkunKasBank $saldo): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarTransaksiKasBank::KOLOM_URUT, DaftarTransaksiKasBank::URUT_BAWAAN, DaftarTransaksiKasBank::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Akuntansi/KasBank/Daftar', 'Transaksi', fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh()), fn (): array => [
            'Saldo' => $saldo->Ambil($this->IdOutletBoleh()),
            'OpsiJenis' => $this->AmbilOpsiJenis(),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
            'Izin' => ['Kelola' => $this->CekIzinKelola()],
        ]);
    }

    /** Halaman penuh "Catat transaksi kas & bank" (pola sama dengan Tambah produk): opsi yang dibutuhkan formulir saja. */
    public function Buat(DaftarAkunPilihan $akun): Response
    {
        return Inertia::render('Kelola/Akuntansi/KasBank/Buat', [
            'OpsiJenis' => $this->AmbilOpsiJenis(),
            'OpsiOutlet' => $this->AmbilOpsiOutlet(),
            'OpsiAkun' => $akun->AmbilUntukKasBank(),
            'WajibOutlet' => $this->IdOutletBoleh() !== null,
            'Lampiran' => ['Ekstensi' => (array) config('akuntansi.EkstensiLampiran'), 'UkuranMaksimalKb' => (int) config('akuntansi.UkuranMaksimalLampiranKb')],
        ]);
    }

    public function Simpan(SimpanTransaksiKasBankPermintaan $permintaan, SimpanTransaksiKasBank $simpan): RedirectResponse
    {
        $uuidOutlet = $permintaan->AmbilUuidOutlet();

        if ($uuidOutlet === null && $this->IdOutletBoleh() !== null) {
            return back()->withErrors(['UuidOutlet' => 'Pilih outlet transaksi ini.'])->withInput();
        }

        $transaksi = $simpan->Jalankan($permintaan->AmbilData($uuidOutlet === null ? null : $this->CariOutlet($uuidOutlet)->Id, $this->Pelaku()->Id));

        return to_route('kelola.akuntansi.kas-bank.detail', ['transaksiKasBank' => $transaksi->Uuid])->with('Kilat', "{$transaksi->Jenis->AmbilLabel()} {$transaksi->Nomor} disimpan dan dijurnal.");
    }

    public function Detail(string $transaksiKasBank, DetailTransaksiKasBank $detail): Response
    {
        $props = $detail->Ambil($transaksiKasBank, $this->IdOutletBoleh());
        abort_if($props === null, 404);

        return Inertia::render('Kelola/Akuntansi/KasBank/Detail', [...$props, 'Izin' => ['Kelola' => $this->CekIzinKelola()]]);
    }

    public function Balikkan(string $transaksiKasBank, BalikkanTransaksiKasBankPermintaan $permintaan, DetailTransaksiKasBank $detail, BalikkanTransaksiKasBank $balikkan): RedirectResponse
    {
        $asal = $this->Cari($transaksiKasBank, $detail);
        $pembalik = $balikkan->Jalankan($asal, $permintaan->AmbilTanggal(), $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.akuntansi.kas-bank.detail', ['transaksiKasBank' => $pembalik->Uuid])->with('Kilat', "{$asal->Nomor} dibalik oleh {$pembalik->Nomor}.");
    }

    public function Lampiran(string $transaksiKasBank, DetailTransaksiKasBank $detail, PenyimpanLampiranKasBank $penyimpan): StreamedResponse
    {
        return $penyimpan->Unduh($this->Cari($transaksiKasBank, $detail));
    }

    /** @return list<array{Nilai: string, Label: string}> */
    private function AmbilOpsiJenis(): array
    {
        return array_map(fn (JenisTransaksiKasBank $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()], JenisTransaksiKasBank::cases());
    }

    private function Cari(string $uuid, DetailTransaksiKasBank $detail): TransaksiKasBank
    {
        $transaksi = $detail->Cari($uuid, $this->IdOutletBoleh());
        abort_unless($transaksi instanceof TransaksiKasBank, 404);

        return $transaksi;
    }
}
