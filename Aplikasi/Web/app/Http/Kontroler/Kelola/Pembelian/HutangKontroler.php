<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pembelian;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pembelian\Aksi\BatalkanPembayaranHutang;
use App\Domain\Pembelian\Aksi\SimpanPembayaranHutang;
use App\Domain\Pembelian\Enum\KelompokUmurHutang;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Kueri\DaftarDokumenPembelian;
use App\Domain\Pembelian\Kueri\DaftarPemasok;
use App\Domain\Pembelian\Kueri\DetailPembelian;
use App\Domain\Pembelian\Layanan\PenyimpanLampiranPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\PembayaranHutang;
use App\Http\Permintaan\Kelola\Pembelian\AlasanPembelianPermintaan;
use App\Http\Permintaan\Kelola\Pembelian\SimpanPembayaranHutangPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hutang & pembayaran hutang (F-04 fase 1): daftar hutang terbuka dengan umur 0–30/31–60/61–90/>90 hari
 * (`/kelola/pembelian/hutang`), daftar/form/detail/pembatalan pembayaran (`/kelola/pembelian/pembayaran`).
 */
final class HutangKontroler extends DasarPembelianKontroler
{
    public function Hutang(Request $permintaan, DaftarDokumenPembelian $daftar, DaftarPemasok $pemasok): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarDokumenPembelian::KOLOM_URUT, DaftarDokumenPembelian::URUT_BAWAAN_HUTANG, DaftarDokumenPembelian::KOLOM_SARING);
        $hariIni = app(TanggalBisnisOutlet::class)->Hitung(null);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Pembelian/Hutang/Daftar', 'Hutang', fn (): array => $daftar->Hutang($tabel, $this->IdOutletBoleh(), $hariIni), fn (): array => [
            'OpsiUmur' => self::Opsi(KelompokUmurHutang::class),
            'OpsiPemasok' => $pemasok->AmbilPilihan(),
            'HariIni' => $hariIni->format('Y-m-d'),
            'Izin' => $this->AmbilIzinPembelian(),
        ]);
    }

    public function Daftar(Request $permintaan, DaftarDokumenPembelian $daftar, DaftarPemasok $pemasok): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarDokumenPembelian::KOLOM_URUT, DaftarDokumenPembelian::URUT_BAWAAN, DaftarDokumenPembelian::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Pembelian/Pembayaran/Daftar', 'Pembayaran', fn (): array => $daftar->Pembayaran($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiStatus' => self::Opsi(StatusDokumenPembelian::class),
            'OpsiPemasok' => $pemasok->AmbilPilihan(),
            'Izin' => $this->AmbilIzinPembelian(),
        ]);
    }

    public function Buat(Request $permintaan, DaftarPemasok $pemasok, DaftarAkunPilihan $akun, DetailPembelian $detail): Response
    {
        $uuidPemasok = $permintaan->query('pemasok');
        $terpilih = is_string($uuidPemasok) && $uuidPemasok !== '' ? $this->CariPemasok($uuidPemasok) : null;

        return Inertia::render('Kelola/Pembelian/Pembayaran/Form', [
            'OpsiPemasok' => $pemasok->AmbilPilihan(),
            'OpsiAkun' => array_map(fn (array $a): array => ['Uuid' => $a['Uuid'], 'Kode' => $a['Kode'], 'Nama' => $a['Nama']], $akun->AmbilKasBank()),
            'UuidPemasok' => $terpilih?->Uuid,
            'UuidFakturAwal' => is_string($permintaan->query('faktur')) ? $permintaan->query('faktur') : null,
            'Faktur' => $terpilih === null ? [] : $detail->FakturTerbuka($terpilih->Id, $this->IdOutletBoleh()),
            'HariIni' => $this->HariIni(),
            'Lampiran' => PenerimaanBarangKontroler::AturanLampiran(),
        ]);
    }

    public function Simpan(SimpanPembayaranHutangPermintaan $permintaan, SimpanPembayaranHutang $simpan): RedirectResponse
    {
        $data = $permintaan->AmbilData($this->Pelaku()->Id);

        foreach (array_keys($data->alokasi) as $uuid) {
            $this->CariDokumen(FakturPembelian::class, (string) $uuid);
        }

        $p = $simpan->Jalankan($data);

        return to_route('kelola.pembelian.pembayaran.detail', ['pembayaran' => $p->Uuid])->with('Kilat', "{$p->Nomor} diposting. Sisa hutang faktur sudah berkurang.");
    }

    public function Detail(string $pembayaran, DetailPembelian $detail): Response
    {
        $p = $this->CariDokumen(PembayaranHutang::class, $pembayaran);
        $izin = $this->AmbilIzinPembelian();

        return Inertia::render('Kelola/Pembelian/Pembayaran/Detail', [
            ...$detail->Pembayaran($p),
            'Izin' => $izin,
            'Tindakan' => ['Batalkan' => $izin['Kelola'] && $p->Status === StatusDokumenPembelian::Diposting && ! $p->BelanjaStok && ! $p->Kompensasi],
        ]);
    }

    public function Batalkan(AlasanPembelianPermintaan $permintaan, string $pembayaran, BatalkanPembayaranHutang $batalkan): RedirectResponse
    {
        $p = $batalkan->Jalankan($this->CariDokumen(PembayaranHutang::class, $pembayaran), $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.pembelian.pembayaran.detail', ['pembayaran' => $p->Uuid])->with('Kilat', "{$p->Nomor} dibatalkan. Sisa hutang faktur dikembalikan.");
    }

    public function Lampiran(string $pembayaran, PenyimpanLampiranPembelian $penyimpan): StreamedResponse
    {
        $p = $this->CariDokumen(PembayaranHutang::class, $pembayaran);

        return $penyimpan->Unduh($p->PathLampiran, $p->NamaLampiran, $p->MimeLampiran);
    }
}
