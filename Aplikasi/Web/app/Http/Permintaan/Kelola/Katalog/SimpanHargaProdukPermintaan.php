<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Harga dasar & bertingkat produk (E.6): `{ Satuan: { UuidProdukSatuan, Harga: BarisHarga[] }[] }`. Uang & jumlah
 * string desimal. Urutan satuan dipertahankan, sehingga galat Aksi `Satuan.{i}.Harga.{j}…` cocok dengan body.
 */
final class SimpanHargaProdukPermintaan extends FormRequest
{
    public const POLA_UANG = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    public const POLA_KUANTITAS = 'regex:/^\d{1,14}(\.\d{1,4})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Satuan' => ['present', 'array', 'max:50'],
            'Satuan.*.UuidProdukSatuan' => ['required', 'ulid', 'distinct'],
            'Satuan.*.Harga' => ['present', 'array', 'max:20'],
            'Satuan.*.Harga.*.JumlahMinimum' => ['required', 'string', self::POLA_KUANTITAS],
            'Satuan.*.Harga.*.Harga' => ['required', 'string', self::POLA_UANG],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Satuan.*.Harga.*.JumlahMinimum.regex' => 'Jumlah minimum berupa angka, maksimal 4 angka desimal.',
            'Satuan.*.Harga.*.Harga.regex' => 'Harga berupa angka tanpa titik ribuan, misal 15000 atau 15000.50.',
        ];
    }

    /**
     * `[IdProdukSatuan => baris]` dalam urutan body. Satuan yang bukan milik produk = galat di bidangnya.
     *
     * @return array<int, list<DataBarisHarga>>
     *
     * @throws ValidationException
     */
    public function AmbilPerSatuan(Produk $produk): array
    {
        $idSatuan = ProdukSatuan::query()->where('IdProduk', $produk->Id)->pluck('Id', 'Uuid');
        $hasil = [];

        /** @var list<array{UuidProdukSatuan: string, Harga: list<array{JumlahMinimum: string, Harga: string}>}> $satuan */
        $satuan = array_values((array) $this->input('Satuan', []));

        foreach ($satuan as $i => $baris) {
            $id = $idSatuan->get((string) $baris['UuidProdukSatuan']);

            if (! is_int($id)) {
                throw ValidationException::withMessages(["Satuan.{$i}.UuidProdukSatuan" => 'Satuan produk ini sudah berubah. Muat ulang halaman.']);
            }

            $hasil[$id] = self::KeBarisHarga((array) $baris['Harga']);
        }

        return $hasil;
    }

    /**
     * @param  array<mixed>  $harga
     * @return list<DataBarisHarga>
     */
    public static function KeBarisHarga(array $harga): array
    {
        return array_values(array_map(fn (array $baris): DataBarisHarga => new DataBarisHarga(
            Kuantitas::Dari((string) $baris['JumlahMinimum']),
            Uang::Dari((string) $baris['Harga']),
        ), array_filter($harga, 'is_array')));
    }
}
