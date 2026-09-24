<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Katalog;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Aksi\BatalkanImporProduk;
use App\Domain\Katalog\Impor\Aksi\LanjutkanImporProduk;
use App\Domain\Katalog\Impor\Aksi\SimpanPemetaanImpor;
use App\Domain\Katalog\Impor\Aksi\TerapkanImporProduk;
use App\Domain\Katalog\Impor\Aksi\UnggahBerkasImpor;
use App\Domain\Katalog\Impor\Data\DataPresetImpor;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Kueri\DaftarImporProduk;
use App\Domain\Katalog\Impor\Kueri\DetailImporProduk;
use App\Domain\Katalog\Impor\Layanan\PembacaBerkasTabel;
use App\Domain\Katalog\Impor\Layanan\PembacaPresetImpor;
use App\Domain\Katalog\Impor\Layanan\PenulisEksporProduk;
use App\Domain\Katalog\Impor\Layanan\PenulisLaporanImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Layanan\OpsiKelompokPajakKatalog;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Http\Permintaan\Kelola\Katalog\SimpanPemetaanImporPermintaan;
use App\Http\Permintaan\Kelola\Katalog\UnggahImporProdukPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor produk Excel/CSV F-03 (BR-03.6, E.10): riwayat & unggah, templat, detail (pemetaan → pratinjau → proses),
 * status polling, terapkan/lanjutkan/batalkan, dan laporan galat xlsx/csv. Semua `{imporProduk}` dicari di scope
 * tenant aktif (impor tenant lain = 404). Izin `produk.kelola`; kolom harga butuh `produk.harga.ubah`.
 */
final class ImporProdukKontroler extends DasarKatalogKontroler
{
    public function Daftar(Request $permintaan, DaftarImporProduk $daftar, PembacaPresetImpor $preset, PastikanBatasPaket $batasPaket, PemakaianSku $pemakaianSku): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarImporProduk::KOLOM_URUT, DaftarImporProduk::URUT_BAWAAN, DaftarImporProduk::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Produk/Impor/Daftar', 'Riwayat', fn (): array => $daftar->AmbilTabel($tabel), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusImporProduk $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusImporProduk::cases()),
            'Preset' => array_map(fn (DataPresetImpor $p): array => ['Kode' => $p->kode, 'Nama' => $p->nama, 'Keterangan' => $p->keterangan, 'Asumsi' => $p->asumsi], $preset->AmbilSemua()),
            'BatasBerkas' => [
                'UkuranMaksimalKb' => (int) config('katalog.Impor.UkuranMaksimalKb', 10240),
                'MaksimalBaris' => (int) config('katalog.Impor.MaksimalBaris', 20000),
                'Ekstensi' => [PembacaBerkasTabel::FORMAT_XLSX, PembacaBerkasTabel::FORMAT_CSV],
            ],
            'BatasSku' => $batasPaket->AmbilRingkasan($this->IdTenant(), 'BatasSku', $pemakaianSku->Hitung()),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Templat(Request $permintaan, PenulisEksporProduk $penulis): StreamedResponse
    {
        return $penulis->AlirkanTemplat($permintaan->query('format') === 'csv' ? 'csv' : 'xlsx');
    }

    public function Unggah(UnggahImporProdukPermintaan $permintaan, UnggahBerkasImpor $unggah): RedirectResponse
    {
        $impor = $unggah->Jalankan($permintaan->AmbilBerkas(), $permintaan->string('Sumber')->toString(), $this->Pelaku()->Id);

        return redirect()->route('kelola.produk.impor.detail', ['imporProduk' => $impor->Uuid])
            ->with('Kilat', "Berkas {$impor->NamaBerkas} diunggah. Periksa pemetaan kolom.");
    }

    public function Detail(string $imporProduk, DaftarImporProduk $daftar, DetailImporProduk $detail, OpsiKelompokPajakKatalog $kelompokPajak): Response
    {
        $impor = $this->CariImpor($imporProduk);

        return Inertia::render('Kelola/Produk/Impor/Detail', [
            'Impor' => $daftar->AmbilRingkasan($impor),
            'Pemetaan' => $detail->AmbilPemetaan($impor),
            'Pratinjau' => $detail->AmbilPratinjau($impor),
            'KelompokPajak' => $kelompokPajak->AmbilOpsiHalaman(),
            'Jenis' => JenisProduk::AmbilDaftarAturan(),
            'Izin' => $this->AmbilIzinKatalog(),
        ]);
    }

    public function Status(string $imporProduk, DetailImporProduk $detail): JsonResponse
    {
        return response()->json($detail->AmbilStatus($this->CariImpor($imporProduk)));
    }

    public function SimpanPemetaan(string $imporProduk, SimpanPemetaanImporPermintaan $permintaan, SimpanPemetaanImpor $simpan): RedirectResponse
    {
        $impor = $simpan->Jalankan($this->CariImpor($imporProduk), $permintaan->AmbilPemetaan(), $permintaan->AmbilOpsi(), $this->CekIzin(IzinTenant::ProdukHargaUbah));

        return redirect()->route('kelola.produk.impor.detail', ['imporProduk' => $impor->Uuid]);
    }

    public function Terapkan(string $imporProduk, TerapkanImporProduk $terapkan): RedirectResponse
    {
        $impor = $terapkan->Jalankan($this->CariImpor($imporProduk));

        return redirect()->route('kelola.produk.impor.detail', ['imporProduk' => $impor->Uuid]);
    }

    public function Lanjutkan(string $imporProduk, LanjutkanImporProduk $lanjutkan): RedirectResponse
    {
        $impor = $lanjutkan->Jalankan($this->CariImpor($imporProduk));

        return redirect()->route('kelola.produk.impor.detail', ['imporProduk' => $impor->Uuid]);
    }

    public function Batalkan(string $imporProduk, BatalkanImporProduk $batalkan): RedirectResponse
    {
        $impor = $batalkan->Jalankan($this->CariImpor($imporProduk));

        return redirect()->route('kelola.produk.impor.detail', ['imporProduk' => $impor->Uuid])->with('Kilat', 'Impor dibatalkan.');
    }

    public function Laporan(string $imporProduk, Request $permintaan, PenulisLaporanImpor $penulis): StreamedResponse
    {
        return $penulis->Alirkan(
            $this->CariImpor($imporProduk),
            $permintaan->query('jenis') === 'semua' ? 'semua' : 'galat',
            $permintaan->query('format') === 'csv' ? 'csv' : 'xlsx',
        );
    }

    private function CariImpor(string $uuid): ImporProduk
    {
        return ImporProduk::query()->where('Uuid', $uuid)->firstOrFail();
    }
}
