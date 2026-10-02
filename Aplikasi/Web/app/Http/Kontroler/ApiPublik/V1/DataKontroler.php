<?php

declare(strict_types=1);

namespace App\Http\Kontroler\ApiPublik\V1;

use App\Domain\Integrasi\ApiPublik\Layanan\PenyusunDataPenjualanApi;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\ProdukUntukApiPublik;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Pelanggan\Kueri\PelangganUntukApiPublik;
use App\Domain\Penjualan\Kueri\PenjualanUntukApiPublik;
use App\Domain\Persediaan\Kueri\StokUntukApiPublik;
use App\Http\Respons\GalatApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * X7 Open API v1 bagian 1 (baca): produk, stok, penjualan, pelanggan. Halaman berbasis kursor (`kursor` buram dari
 * `Kursor.Berikutnya`, `per` 1–200, bawaan 50); respons `{Data, Kursor: {Berikutnya}}`. Uuid dipakai sebagai pengenal;
 * Id internal tidak pernah keluar. Tenant aktif & cakupan sudah ditetapkan `AutentikasiTokenApi`.
 */
final class DataKontroler extends Controller
{
    public const PER_BAWAAN = 50;

    public const PER_MAKSIMUM = 200;

    /** Rentang tanggal penjualan per permintaan (hari). */
    public const HARI_MAKSIMUM = 92;

    public function Produk(Request $permintaan, ProdukUntukApiPublik $produk): JsonResponse
    {
        [$setelah, $per] = self::BacaHalaman($permintaan);

        if ($setelah === null) {
            return self::GalatKursor();
        }

        $hasil = $produk->Daftar($setelah, $per);

        return self::Halaman($hasil['Data'], $hasil['IdTerakhir']);
    }

    public function ProdukSatu(string $uuidProduk, ProdukUntukApiPublik $produk): JsonResponse
    {
        $data = $produk->Daftar(0, 1, $uuidProduk)['Data'];

        return $data === [] ? GalatApi::Buat('TidakDitemukan', 'Produk tidak ditemukan.', 404) : response()->json(['Data' => $data[0]]);
    }

    public function Stok(Request $permintaan, StokUntukApiPublik $stok, InfoGudang $gudang, InfoProdukStok $infoProduk): JsonResponse
    {
        [$setelah, $per] = self::BacaHalaman($permintaan);

        if ($setelah === null) {
            return self::GalatKursor();
        }

        $uuidGudang = $permintaan->query('gudang');
        $idGudang = null;

        if (is_string($uuidGudang) && $uuidGudang !== '') {
            $info = $gudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null;
            $idGudang = $info === null ? [] : [$info->id];
        }

        $hasil = $stok->Daftar($setelah, $per, $idGudang);
        $produk = $infoProduk->AmbilBanyak(array_values(array_unique(array_column($hasil['Data'], 'IdProduk'))), denganTerhapus: true);
        $lokasi = $gudang->AmbilBanyak(array_values(array_unique(array_column($hasil['Data'], 'IdGudang'))));

        return self::Halaman(array_map(fn (array $b): array => [
            'UuidProduk' => ($produk[$b['IdProduk']] ?? null)?->uuid,
            'Sku' => ($produk[$b['IdProduk']] ?? null)?->sku,
            'NamaProduk' => ($produk[$b['IdProduk']] ?? null)?->nama,
            'UuidGudang' => ($lokasi[$b['IdGudang']] ?? null)?->uuid,
            'NamaGudang' => ($lokasi[$b['IdGudang']] ?? null)?->nama,
            'JumlahTersedia' => $b['JumlahTersedia'],
            'JumlahDipesan' => $b['JumlahDipesan'],
            'DiubahPada' => $b['DiubahPada'],
        ], $hasil['Data']), $hasil['IdTerakhir']);
    }

    public function Penjualan(Request $permintaan, PenjualanUntukApiPublik $penjualan, PenyusunDataPenjualanApi $penyusun): JsonResponse
    {
        [$setelah, $per] = self::BacaHalaman($permintaan);

        if ($setelah === null) {
            return self::GalatKursor();
        }

        $dari = self::BacaTanggal($permintaan->query('dari'));
        $sampai = self::BacaTanggal($permintaan->query('sampai'));

        if ($dari === false || $sampai === false || $dari === null || $sampai === null || $sampai->lt($dari) || $dari->diffInDays($sampai) >= self::HARI_MAKSIMUM) {
            return GalatApi::Buat('RentangTanggalTidakValid', 'Isi dari & sampai (YYYY-MM-DD), sampai ≥ dari, paling panjang '.self::HARI_MAKSIMUM.' hari.', 422);
        }

        $hasil = $penjualan->Daftar($setelah, $per, $dari, $sampai);

        return self::Halaman($penyusun->Petakan($hasil['Data']), $hasil['IdTerakhir']);
    }

    public function PenjualanSatu(string $uuidPenjualan, PenjualanUntukApiPublik $penjualan, PenyusunDataPenjualanApi $penyusun): JsonResponse
    {
        $data = $penjualan->Daftar(0, 1, uuid: $uuidPenjualan)['Data'];

        return $data === []
            ? GalatApi::Buat('TidakDitemukan', 'Penjualan tidak ditemukan.', 404)
            : response()->json(['Data' => $penyusun->Petakan($data)[0]]);
    }

    public function Pelanggan(Request $permintaan, PelangganUntukApiPublik $pelanggan): JsonResponse
    {
        [$setelah, $per] = self::BacaHalaman($permintaan);

        if ($setelah === null) {
            return self::GalatKursor();
        }

        $hasil = $pelanggan->Daftar($setelah, $per);

        return self::Halaman($hasil['Data'], $hasil['IdTerakhir']);
    }

    /**
     * @return array{0: int|null, 1: int} null = kursor rusak
     */
    private static function BacaHalaman(Request $permintaan): array
    {
        $per = filter_var($permintaan->query('per', (string) self::PER_BAWAAN), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::PER_MAKSIMUM]]);
        $per = is_int($per) ? $per : self::PER_BAWAAN;
        $kursor = $permintaan->query('kursor');

        if (! is_string($kursor) || $kursor === '') {
            return [0, $per];
        }

        $isi = base64_decode(strtr($kursor, '-_', '+/'), true);
        $data = is_string($isi) ? json_decode($isi, true) : null;

        return [is_array($data) && is_int($data['Id'] ?? null) && $data['Id'] > 0 ? $data['Id'] : null, $per];
    }

    private static function BacaTanggal(mixed $nilai): CarbonImmutable|false|null
    {
        if ($nilai === null) {
            return null;
        }

        if (! is_string($nilai) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) !== 1) {
            return false;
        }

        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $nilai);

        return $tanggal instanceof CarbonImmutable && $tanggal->toDateString() === $nilai ? $tanggal : false;
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private static function Halaman(array $data, ?int $idTerakhir): JsonResponse
    {
        return response()->json([
            'Data' => $data,
            'Kursor' => ['Berikutnya' => $idTerakhir === null ? null : rtrim(strtr(base64_encode((string) json_encode(['Id' => $idTerakhir])), '+/', '-_'), '=')],
        ]);
    }

    private static function GalatKursor(): JsonResponse
    {
        return GalatApi::Buat('KursorTidakValid', 'Kursor tidak valid. Mulai lagi tanpa kursor.', 422);
    }
}
