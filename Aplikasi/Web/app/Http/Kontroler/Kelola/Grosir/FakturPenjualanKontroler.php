<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Grosir;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Penjualan\Aksi\BatalkanFakturPenjualan;
use App\Domain\Penjualan\Aksi\BuatFakturPenjualan;
use App\Domain\Penjualan\Enum\StatusFakturPenjualan;
use App\Domain\Penjualan\Enum\StatusSuratJalan;
use App\Domain\Penjualan\Kueri\DaftarDokumenGrosir;
use App\Domain\Penjualan\Kueri\DetailGrosir;
use App\Domain\Penjualan\Model\FakturPenjualan;
use App\Http\Permintaan\Kelola\Grosir\AlasanGrosirPermintaan;
use App\Http\Permintaan\Kelola\Grosir\BuatFakturPenjualanPermintaan;
use App\Http\Permintaan\Kelola\Grosir\NomorFakturPajakPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Faktur penjualan grosir (F-12, §9.7, `/kelola/grosir/faktur`): daftar (dengan sisa piutangnya, yang dibaca dari
 * `Piutang` dan bukan disalin ke faktur), detail, penerbitan dari surat jalan terpilih (BR-12.4), pengisian nomor
 * Faktur Pajak, dan pembatalan.
 */
final class FakturPenjualanKontroler extends DasarGrosirKontroler
{
    public function Daftar(Request $permintaan, DaftarDokumenGrosir $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarDokumenGrosir::KOLOM_URUT, DaftarDokumenGrosir::URUT_BAWAAN, DaftarDokumenGrosir::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Grosir/Faktur/Daftar', 'Faktur', fn (): array => $daftar->Faktur($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiStatus' => self::Opsi(StatusFakturPenjualan::class),
            'Izin' => $this->AmbilIzinGrosir(),
        ]);
    }

    /** Pemilihan surat jalan yang belum difakturkan; batas satu pelanggan/outlet/bulan diperiksa saat diterbitkan. */
    public function Buat(Request $permintaan, DaftarDokumenGrosir $daftar): Response|JsonResponse
    {
        $query = [...$permintaan->query(), 'saring' => [...(array) $permintaan->query('saring', []), 'Difakturkan' => 'Belum', 'Status' => StatusSuratJalan::Diposting->value]];
        $tabel = DataPermintaanTabel::Dari($query, DaftarDokumenGrosir::KOLOM_URUT, DaftarDokumenGrosir::URUT_BAWAAN, DaftarDokumenGrosir::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Grosir/Faktur/Buat', 'SuratJalan', fn (): array => $daftar->SuratJalan($tabel, $this->IdOutletBoleh()), fn (): array => [
            'HariIni' => $this->HariIni(),
            'Izin' => $this->AmbilIzinGrosir(),
        ]);
    }

    public function Simpan(BuatFakturPenjualanPermintaan $permintaan, BuatFakturPenjualan $buat): RedirectResponse
    {
        $faktur = $buat->Jalankan($permintaan->AmbilData(), $this->Pelaku()->Id);

        return to_route('kelola.grosir.faktur.detail', ['faktur' => $faktur->Uuid])
            ->with('Kilat', "Faktur {$faktur->Nomor} diterbitkan. Piutangnya jatuh tempo {$faktur->JatuhTempo->format('Y-m-d')}.");
    }

    public function Detail(string $faktur, DetailGrosir $detail): Response
    {
        $dokumen = $this->CariDokumen(FakturPenjualan::class, $faktur);
        $izin = $this->AmbilIzinGrosir();

        return Inertia::render('Kelola/Grosir/Faktur/Detail', [
            ...$detail->Faktur($dokumen),
            'Izin' => $izin,
            'Tindakan' => [
                'Batalkan' => $izin['Kelola'] && $dokumen->Status === StatusFakturPenjualan::Diposting,
                'UbahNomorPajak' => $izin['Kelola'] && $dokumen->Status === StatusFakturPenjualan::Diposting,
            ],
        ]);
    }

    public function UbahNomorPajak(NomorFakturPajakPermintaan $permintaan, string $faktur): RedirectResponse
    {
        $dokumen = $this->CariDokumen(FakturPenjualan::class, $faktur);

        if ($dokumen->Status !== StatusFakturPenjualan::Diposting) {
            return to_route('kelola.grosir.faktur.detail', ['faktur' => $dokumen->Uuid])
                ->withErrors(['Umum' => 'Faktur yang sudah dibatalkan tidak bisa diubah.']);
        }

        $dokumen->NomorFakturPajak = $permintaan->AmbilNomor();
        $dokumen->DiubahOleh = $this->Pelaku()->Id;
        $dokumen->save();

        return to_route('kelola.grosir.faktur.detail', ['faktur' => $dokumen->Uuid])->with('Kilat', 'Nomor Faktur Pajak disimpan.');
    }

    public function Batalkan(AlasanGrosirPermintaan $permintaan, string $faktur, BatalkanFakturPenjualan $batalkan): RedirectResponse
    {
        $dokumen = $this->CariDokumen(FakturPenjualan::class, $faktur);
        $hasil = $batalkan->Jalankan($dokumen->Uuid, $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.grosir.faktur.detail', ['faktur' => $hasil->Uuid])
            ->with('Kilat', "{$hasil->Nomor} dibatalkan: piutangnya dibatalkan dan surat jalannya bisa difakturkan ulang.");
    }
}
