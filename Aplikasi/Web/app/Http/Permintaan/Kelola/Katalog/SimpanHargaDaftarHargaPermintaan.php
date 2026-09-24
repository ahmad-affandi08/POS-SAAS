<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Harga satuan produk di satu daftar harga (E.6 & E.7), dua bentuk body:
 * - halaman daftar harga `PUT /kelola/daftar-harga/{uuid}/harga`: `{ Baris: { UuidProdukSatuan, Harga[] }[] }`
 *   (galat `Baris.{i}.Harga.{j}…`);
 * - halaman harga produk `PUT /kelola/produk/{uuid}/harga/daftar-harga/{uuid}`: `{ Harga: (BarisHarga &
 *   {UuidProdukSatuan})[] }` rata untuk **semua** satuan produk (satuan tanpa baris = dikeluarkan dari daftar;
 *   galat `Harga.{k}…`, `k` = indeks baris rata).
 */
final class SimpanHargaDaftarHargaPermintaan extends FormRequest
{
    public const RUTE_PRODUK = 'kelola.produk.harga.daftar-harga.simpan';

    /** @var array<string, string> peta bidang galat Aksi `Satuan.{i}.Harga.{j}` → bidang body (bentuk produk) */
    private array $petaBidang = [];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        if ($this->routeIs(self::RUTE_PRODUK)) {
            return [
                'Harga' => ['present', 'array', 'max:500'],
                'Harga.*.UuidProdukSatuan' => ['required', 'ulid'],
                'Harga.*.JumlahMinimum' => ['required', 'string', SimpanHargaProdukPermintaan::POLA_KUANTITAS],
                'Harga.*.Harga' => ['required', 'string', SimpanHargaProdukPermintaan::POLA_UANG],
            ];
        }

        return [
            'Baris' => ['present', 'array', 'max:100'],
            'Baris.*.UuidProdukSatuan' => ['required', 'ulid', 'distinct'],
            'Baris.*.Harga' => ['present', 'array', 'max:20'],
            'Baris.*.Harga.*.JumlahMinimum' => ['required', 'string', SimpanHargaProdukPermintaan::POLA_KUANTITAS],
            'Baris.*.Harga.*.Harga' => ['required', 'string', SimpanHargaProdukPermintaan::POLA_UANG],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $jumlah = 'Jumlah minimum berupa angka, maksimal 4 angka desimal.';
        $harga = 'Harga berupa angka tanpa titik ribuan, misal 15000 atau 15000.50.';

        return [
            'Harga.*.JumlahMinimum.regex' => $jumlah,
            'Harga.*.Harga.regex' => $harga,
            'Baris.*.Harga.*.JumlahMinimum.regex' => $jumlah,
            'Baris.*.Harga.*.Harga.regex' => $harga,
        ];
    }

    /**
     * Bentuk halaman daftar harga: `[IdProdukSatuan => baris]` dalam urutan body; satuan tidak dikenal (tenant lain,
     * produk terhapus) = galat di bidangnya.
     *
     * @return array<int, list<DataBarisHarga>>
     *
     * @throws ValidationException
     */
    public function AmbilPerSatuanDariBaris(): array
    {
        /** @var list<array{UuidProdukSatuan: string, Harga: list<array{JumlahMinimum: string, Harga: string}>}> $baris */
        $baris = array_values((array) $this->input('Baris', []));
        $idSatuan = ProdukSatuan::query()->whereIn('Uuid', array_map(fn (array $b): string => (string) $b['UuidProdukSatuan'], $baris))->whereHas('Produk')->pluck('Id', 'Uuid');
        $hasil = [];

        foreach ($baris as $i => $item) {
            $id = $idSatuan->get((string) $item['UuidProdukSatuan']);

            if (! is_int($id)) {
                throw ValidationException::withMessages(["Baris.{$i}.UuidProdukSatuan" => 'Satuan produk ini tidak ditemukan. Muat ulang halaman.']);
            }

            $hasil[$id] = SimpanHargaProdukPermintaan::KeBarisHarga((array) $item['Harga']);
        }

        return $hasil;
    }

    /**
     * Bentuk halaman harga produk: semua satuan produk (urut Id), baris dikelompokkan per satuan.
     *
     * @return array<int, list<DataBarisHarga>>
     *
     * @throws ValidationException
     */
    public function AmbilPerSatuanDariProduk(Produk $produk): array
    {
        $satuan = ProdukSatuan::query()->where('IdProduk', $produk->Id)->orderBy('Id')->pluck('Id', 'Uuid');
        /** @var list<array{UuidProdukSatuan: string, JumlahMinimum: string, Harga: string}> $rata */
        $rata = array_values((array) $this->input('Harga', []));
        $kelompok = [];
        $indeksRata = [];

        foreach ($rata as $k => $baris) {
            $id = $satuan->get((string) $baris['UuidProdukSatuan']);

            if (! is_int($id)) {
                throw ValidationException::withMessages(["Harga.{$k}.UuidProdukSatuan" => 'Satuan produk ini sudah berubah. Muat ulang halaman.']);
            }

            $kelompok[$id][] = $baris;
            $indeksRata[$id][] = $k;
        }

        $hasil = [];
        $i = 0;
        $this->petaBidang = [];

        foreach ($satuan as $id) {
            $hasil[$id] = SimpanHargaProdukPermintaan::KeBarisHarga($kelompok[$id] ?? []);
            $this->petaBidang["Satuan.{$i}"] = 'Harga';

            foreach ($indeksRata[$id] ?? [] as $j => $k) {
                $this->petaBidang["Satuan.{$i}.Harga.{$j}"] = "Harga.{$k}";
            }

            $i++;
        }

        return $hasil;
    }

    /** Memetakan bidang galat Aksi (`Satuan.{i}.Harga.{j}.X`) ke bidang body permintaan ini. */
    public function PetakanGalat(PelanggaranAturanBisnis $galat): PelanggaranAturanBisnis
    {
        if (! $this->routeIs(self::RUTE_PRODUK)) {
            $bidang = (string) preg_replace('/^Satuan\./', 'Baris.', $galat->bidang);
        } elseif (preg_match('/^(Satuan\.\d+\.Harga\.\d+)(\..+)?$/', $galat->bidang, $cocok) === 1 && isset($this->petaBidang[$cocok[1]])) {
            $bidang = $this->petaBidang[$cocok[1]].($cocok[2] ?? '');
        } elseif (preg_match('/^(Satuan\.\d+)/', $galat->bidang, $cocok) === 1 && isset($this->petaBidang[$cocok[1]])) {
            $bidang = $this->petaBidang[$cocok[1]];
        } else {
            $bidang = $galat->bidang;
        }

        return new PelanggaranAturanBisnis($galat->kode, $galat->getMessage(), $bidang, $galat->statusHttp, $galat->detail);
    }
}
