<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Persediaan\Kueri\KartuStok;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman kartu stok, izin `persediaan.lihat` (DesainF05a D; tipe FE `PropsKartuStok`). Query: `produk` & `gudang`
 * (Uuid), `dari` & `sampai` (`YYYY-MM-DD`, bawaan awal bulan s.d. hari ini menurut tanggal bisnis outlet lokasi),
 * `halaman` & `perHalaman` (TabelData D-16). Produk tenant lain = 404; lokasi tenant lain atau di outlet di luar akses = 404. Tanpa produk atau
 * lokasi terpilih: `Mutasi`, `SaldoAwal`, `SaldoAkhir` null (FE menampilkan pemilih).
 */
final class KartuStokKontroler extends DasarPersediaanKontroler
{
    public function Tampilkan(Request $permintaan, KartuStok $kartu, InfoProdukStok $infoProduk, TanggalBisnisOutlet $tanggalBisnis): Response|JsonResponse
    {
        $uuidProduk = self::AmbilTeks($permintaan->query('produk'));
        $uuidGudang = self::AmbilTeks($permintaan->query('gudang'));

        $produk = null;

        if ($uuidProduk !== null) {
            $produk = $infoProduk->AmbilDariUuid([$uuidProduk])[$uuidProduk] ?? null;
            abort_if($produk === null, 404);
        }

        $gudang = $uuidGudang === null ? null : $this->CariGudangBoleh($uuidGudang);
        $hariIni = $tanggalBisnis->Hitung($gudang?->idOutlet);
        $dari = self::UraiTanggal($permintaan->query('dari')) ?? $hariIni->startOfMonth();
        $sampai = self::UraiTanggal($permintaan->query('sampai')) ?? $hariIni;

        if ($dari->greaterThan($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $tabel = DataPermintaanTabel::Dari($permintaan->query(), [], '');
        $isi = $produk !== null && $gudang !== null
            ? $kartu->Ambil($produk->id, $gudang->id, $dari, $sampai, $tabel->halaman, $tabel->perHalaman)
            : ['SaldoAwal' => null, 'SaldoAkhir' => null, 'Mutasi' => null];

        // TabelData (D-16): halaman mutasi berikutnya diminta ke URL yang sama sebagai JSON.
        if (ResponsTabel::MintaData($permintaan)) {
            return response()->json($isi['Mutasi'] ?? ['Data' => [], 'Meta' => ['Halaman' => 1, 'PerHalaman' => $tabel->perHalaman, 'Total' => 0, 'JumlahHalaman' => 1]]);
        }

        return Inertia::render('Kelola/Persediaan/KartuStok', [
            'Produk' => $produk === null ? null : [
                'Uuid' => $produk->uuid,
                'Nama' => $produk->nama,
                'Sku' => $produk->sku,
                'SimbolSatuan' => $produk->simbolSatuan,
                'Pelacakan' => $produk->pelacakan->value,
            ],
            'Gudang' => $gudang === null ? null : [
                'Uuid' => $gudang->uuid,
                'Kode' => $gudang->kode,
                'Nama' => $gudang->nama,
                'Jenis' => $gudang->jenis->value,
                'NamaOutlet' => $gudang->namaOutlet,
                'Aktif' => $gudang->aktif,
            ],
            'Saring' => [
                'UuidProduk' => $produk?->uuid,
                'UuidGudang' => $gudang?->uuid,
                'Dari' => $dari->toDateString(),
                'Sampai' => $sampai->toDateString(),
            ],
            ...$isi,
            'OpsiGudang' => $this->AmbilOpsiGudang(false),
        ]);
    }

    private static function AmbilTeks(mixed $nilai): ?string
    {
        return is_string($nilai) && $nilai !== '' ? $nilai : null;
    }

    private static function UraiTanggal(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $bagian) !== 1) {
            return null;
        }

        if (! checkdate((int) $bagian[2], (int) $bagian[3], (int) $bagian[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $nilai) ?: null;
    }
}
