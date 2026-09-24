<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataAtributVarian;
use App\Domain\Katalog\Data\DataGenerasiVarian;
use App\Domain\Katalog\Enum\JenisProduk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Generasi varian F-03 (E.4): `{ AtributVarian: {Nama, Nilai[]}[], JenisAnak, HargaDasar: "" | "15000" }`.
 */
final class GenerasikanVarianPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'AtributVarian' => ['present', 'array', 'max:3'],
            'AtributVarian.*.Nama' => ['required', 'string', 'max:30'],
            'AtributVarian.*.Nilai' => ['required', 'array', 'min:1', 'max:20'],
            'AtributVarian.*.Nilai.*' => ['required', 'string', 'max:40'],
            'JenisAnak' => ['required', Rule::enum(JenisProduk::class)],
            'HargaDasar' => ['nullable', 'string', SimpanProdukPermintaan::POLA_UANG],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['HargaDasar.regex' => 'Harga berupa angka tanpa titik ribuan, misal 15000.'];
    }

    public function AmbilData(bool $bolehUbahHarga): DataGenerasiVarian
    {
        return new DataGenerasiVarian(
            atribut: array_values(array_map(fn (array $atribut): DataAtributVarian => new DataAtributVarian(
                (string) $atribut['Nama'],
                array_values(array_map('strval', (array) $atribut['Nilai'])),
            ), array_values((array) $this->input('AtributVarian', [])))),
            jenisAnak: JenisProduk::from($this->string('JenisAnak')->toString()),
            hargaDasar: $this->filled('HargaDasar') ? Uang::Dari($this->string('HargaDasar')->toString()) : null,
            bolehUbahHarga: $bolehUbahHarga,
        );
    }
}
