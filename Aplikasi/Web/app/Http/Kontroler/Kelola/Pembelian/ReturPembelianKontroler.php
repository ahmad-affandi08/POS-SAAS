<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pembelian;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Pembelian\Aksi\BatalkanReturPembelian;
use App\Domain\Pembelian\Aksi\SimpanReturPembelian;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Kueri\DaftarDokumenPembelian;
use App\Domain\Pembelian\Kueri\DaftarPemasok;
use App\Domain\Pembelian\Kueri\DetailPembelian;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\ReturPembelian;
use App\Http\Permintaan\Kelola\Pembelian\AlasanPembelianPermintaan;
use App\Http\Permintaan\Kelola\Pembelian\SimpanReturPembelianPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Retur pembelian (F-04 fase 1, `/kelola/pembelian/retur`): daftar, form dari GRN (`?penerimaan=`), detail, pembatalan. */
final class ReturPembelianKontroler extends DasarPembelianKontroler
{
    public function Daftar(Request $permintaan, DaftarDokumenPembelian $daftar, DaftarPemasok $pemasok): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarDokumenPembelian::KOLOM_URUT, DaftarDokumenPembelian::URUT_BAWAAN, DaftarDokumenPembelian::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Pembelian/Retur/Daftar', 'Retur', fn (): array => $daftar->Retur($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiStatus' => self::Opsi(StatusDokumenPembelian::class),
            'OpsiPemasok' => $pemasok->AmbilPilihan(),
            'Izin' => $this->AmbilIzinPembelian(),
        ]);
    }

    public function Buat(Request $permintaan, DetailPembelian $detail): Response|RedirectResponse
    {
        $uuid = $permintaan->query('penerimaan');

        if (! is_string($uuid) || $uuid === '') {
            return to_route('kelola.pembelian.penerimaan.daftar')->with('Kilat', 'Pilih penerimaan barang yang akan diretur, lalu tekan "Retur barang".');
        }

        $grn = $this->CariDokumen(PenerimaanBarang::class, $uuid);

        return Inertia::render('Kelola/Pembelian/Retur/Form', [
            ...$detail->Penerimaan($grn),
            'HariIni' => $this->HariIni(),
        ]);
    }

    public function Simpan(SimpanReturPembelianPermintaan $permintaan, SimpanReturPembelian $simpan): RedirectResponse
    {
        $this->CariDokumen(PenerimaanBarang::class, (string) $permintaan->validated('UuidPenerimaan'));
        $retur = $simpan->Jalankan($permintaan->AmbilData($this->Pelaku()->Id));

        return to_route('kelola.pembelian.retur.detail', ['retur' => $retur->Uuid])->with('Kilat', "{$retur->Nomor} diposting. Stok berkurang dan hutang pemasok dikurangi.");
    }

    public function Detail(string $retur, DetailPembelian $detail): Response
    {
        $r = $this->CariDokumen(ReturPembelian::class, $retur);
        $izin = $this->AmbilIzinPembelian();

        return Inertia::render('Kelola/Pembelian/Retur/Detail', [
            ...$detail->Retur($r),
            'Izin' => $izin,
            'Tindakan' => ['Batalkan' => $izin['Kelola'] && $r->Status === StatusDokumenPembelian::Diposting],
        ]);
    }

    public function Batalkan(AlasanPembelianPermintaan $permintaan, string $retur, BatalkanReturPembelian $batalkan): RedirectResponse
    {
        $r = $batalkan->Jalankan($this->CariDokumen(ReturPembelian::class, $retur), $permintaan->AmbilAlasan(), $this->Pelaku()->Id);

        return to_route('kelola.pembelian.retur.detail', ['retur' => $r->Uuid])->with('Kilat', "{$r->Nomor} dibatalkan. Stok dan hutang dikembalikan.");
    }
}
