<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Piutang;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Aksi\BatalkanPembayaranPiutang;
use App\Domain\Pelanggan\Aksi\SimpanPembayaranPiutang;
use App\Domain\Pelanggan\Enum\KelompokUmurPiutang;
use App\Domain\Pelanggan\Enum\StatusPembayaranPiutang;
use App\Domain\Pelanggan\Kueri\DaftarPiutang;
use App\Domain\Pelanggan\Kueri\DetailPembayaranPiutang;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PembayaranPiutang;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Permintaan\Kelola\Pembelian\AlasanPembelianPermintaan;
use App\Http\Permintaan\Kelola\Piutang\SimpanPembayaranPiutangPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Piutang pelanggan (F-12): daftar piutang terbuka dengan umur 0–30/31–60/61–90/>90 hari (`/kelola/piutang`),
 * daftar/form/detail/pembatalan pelunasan (`/kelola/piutang/pelunasan`). Lihat: `pelanggan.lihat`; pelunasan &
 * pembatalan: `akuntansi.kelola`. Data tenant lain = 404 (`MilikTenant`).
 */
final class PiutangKontroler extends DasarKelolaKontroler
{
    public function Piutang(Request $permintaan, DaftarPiutang $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPiutang::KOLOM_URUT, DaftarPiutang::URUT_BAWAAN, DaftarPiutang::KOLOM_SARING);
        $hariIni = app(TanggalBisnisOutlet::class)->Hitung(null);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Piutang/Daftar', 'Piutang', fn (): array => $daftar->Terbuka($tabel, $hariIni, $this->IdOutletBoleh()), fn (): array => [
            'OpsiUmur' => array_map(fn (KelompokUmurPiutang $k): array => ['Nilai' => $k->value, 'Label' => $k->AmbilLabel()], KelompokUmurPiutang::cases()),
            'OpsiPelanggan' => $daftar->AmbilOpsiPelanggan(),
            'HariIni' => $hariIni->format('Y-m-d'),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function Daftar(Request $permintaan, DaftarPiutang $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPiutang::KOLOM_URUT, DaftarPiutang::URUT_BAWAAN_PELUNASAN, DaftarPiutang::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Piutang/Pelunasan/Daftar', 'Pelunasan', fn (): array => $daftar->Pelunasan($tabel), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusPembayaranPiutang $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusPembayaranPiutang::cases()),
            'Izin' => $this->AmbilIzin(),
        ]);
    }

    public function Buat(Request $permintaan, DaftarPiutang $daftar, DaftarAkunPilihan $akun): Response
    {
        $uuidPelanggan = $permintaan->query('pelanggan');
        $terpilih = is_string($uuidPelanggan) && $uuidPelanggan !== '' ? Pelanggan::query()->where('Uuid', $uuidPelanggan)->firstOrFail() : null;

        return Inertia::render('Kelola/Piutang/Pelunasan/Form', [
            'OpsiPelanggan' => $daftar->AmbilOpsiPelanggan(),
            'OpsiAkun' => array_map(fn (array $a): array => ['Uuid' => $a['Uuid'], 'Kode' => $a['Kode'], 'Nama' => $a['Nama']], $akun->AmbilKasBank()),
            'UuidPelanggan' => $terpilih?->Uuid,
            'NamaPelanggan' => $terpilih?->Nama,
            'UuidPiutangAwal' => is_string($permintaan->query('piutang')) ? $permintaan->query('piutang') : null,
            'Piutang' => $terpilih === null ? [] : $daftar->AmbilTerbukaPelanggan($terpilih->Id),
            'HariIni' => app(TanggalBisnisOutlet::class)->Hitung(null)->format('Y-m-d'),
        ]);
    }

    public function Simpan(SimpanPembayaranPiutangPermintaan $permintaan, SimpanPembayaranPiutang $simpan): RedirectResponse
    {
        $p = $simpan->Jalankan($permintaan->AmbilData($this->Pelaku()->Id));

        return to_route('kelola.piutang.pelunasan.detail', ['pelunasan' => $p->Uuid])->with('Kilat', "{$p->Nomor} diposting. Sisa piutang sudah berkurang.");
    }

    public function Detail(string $pelunasan, DetailPembayaranPiutang $detail): Response
    {
        $p = PembayaranPiutang::query()->where('Uuid', $pelunasan)->firstOrFail();
        $izin = $this->AmbilIzin();

        return Inertia::render('Kelola/Piutang/Pelunasan/Detail', [
            ...$detail->Ambil($p),
            'Izin' => $izin,
            'Tindakan' => ['Batalkan' => $izin['Kelola'] && $p->Status === StatusPembayaranPiutang::Diposting],
        ]);
    }

    public function Batalkan(AlasanPembelianPermintaan $permintaan, string $pelunasan, BatalkanPembayaranPiutang $batalkan): RedirectResponse
    {
        $p = $batalkan->Jalankan(PembayaranPiutang::query()->where('Uuid', $pelunasan)->firstOrFail(), $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.piutang.pelunasan.detail', ['pelunasan' => $p->Uuid])->with('Kilat', "{$p->Nomor} dibatalkan. Sisa piutang dikembalikan.");
    }

    /**
     * @return array{Kelola: bool, LihatJurnal: bool}
     */
    private function AmbilIzin(): array
    {
        $akses = app(AksesPengguna::class);

        return [
            'Kelola' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::AkuntansiKelola),
            'LihatJurnal' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::LaporanKeuanganLihat),
        ];
    }
}
