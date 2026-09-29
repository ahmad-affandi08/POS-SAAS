<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Grosir;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Penjualan\Aksi\BatalkanSuratJalan;
use App\Domain\Penjualan\Enum\StatusSuratJalan;
use App\Domain\Penjualan\Kueri\DaftarDokumenGrosir;
use App\Domain\Penjualan\Kueri\DetailGrosir;
use App\Domain\Penjualan\Model\SuratJalan;
use App\Http\Permintaan\Kelola\Grosir\AlasanGrosirPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Surat jalan grosir (F-12, §9.7, `/kelola/grosir/surat-jalan`): daftar (termasuk saringan **Belum difakturkan** yang
 * dituju butir Kotak Tindakan BR-12.4), detail, dan pembatalan (J-12.3). Surat jalannya dibuat dari halaman SO, karena
 * yang diserahkan selalu barang milik satu pesanan.
 */
final class SuratJalanKontroler extends DasarGrosirKontroler
{
    public function Daftar(Request $permintaan, DaftarDokumenGrosir $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarDokumenGrosir::KOLOM_URUT, DaftarDokumenGrosir::URUT_BAWAAN, DaftarDokumenGrosir::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Grosir/SuratJalan/Daftar', 'SuratJalan', fn (): array => $daftar->SuratJalan($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiStatus' => self::Opsi(StatusSuratJalan::class),
            'HariIni' => $this->HariIni(),
            'Izin' => $this->AmbilIzinGrosir(),
        ]);
    }

    public function Detail(string $suratJalan, DetailGrosir $detail): Response
    {
        $dokumen = $this->CariDokumen(SuratJalan::class, $suratJalan);
        $izin = $this->AmbilIzinGrosir();

        return Inertia::render('Kelola/Grosir/SuratJalan/Detail', [
            ...$detail->SuratJalan($dokumen),
            'Izin' => $izin,
            'Tindakan' => [
                'Batalkan' => $izin['Kelola'] && $dokumen->Status === StatusSuratJalan::Diposting && $dokumen->IdFakturPenjualan === null,
            ],
        ]);
    }

    public function Batalkan(AlasanGrosirPermintaan $permintaan, string $suratJalan, BatalkanSuratJalan $batalkan): RedirectResponse
    {
        $dokumen = $this->CariDokumen(SuratJalan::class, $suratJalan);
        $hasil = $batalkan->Jalankan($dokumen->Uuid, $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.grosir.surat-jalan.detail', ['suratJalan' => $hasil->Uuid])
            ->with('Kilat', "{$hasil->Nomor} dibatalkan: stok kembali dan pengakuan penjualannya dibalik.");
    }
}
