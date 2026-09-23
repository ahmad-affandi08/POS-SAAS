<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\PanduanAwal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-01 langkah 4(a): produk contoh terpilih beserta harga isian pemilik. Harga = string desimal bertitik tanpa
 * pemisah ribuan ("15000" atau "78500.50"); "15.000" ditolak.
 */
final class SimpanProdukContohPermintaan extends FormRequest
{
    public const POLA_HARGA = '/^\d{1,16}(\.\d{1,2})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ProdukContoh' => ['required', 'array', 'min:1', 'max:100'],
            'ProdukContoh.*' => ['array'],
            'ProdukContoh.*.Nama' => ['required', 'string', 'max:150'],
            'ProdukContoh.*.Harga' => ['required', 'string', 'regex:'.self::POLA_HARGA],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ProdukContoh.required' => 'Pilih minimal satu produk contoh.',
            'ProdukContoh.min' => 'Pilih minimal satu produk contoh.',
            'ProdukContoh.*.Harga.required' => 'Isi harga jual.',
            'ProdukContoh.*.Harga.regex' => 'Harga berupa angka tanpa titik ribuan, misal 15000.',
        ];
    }

    /**
     * @return list<array{Nama: string, Harga: string}>
     */
    public function AmbilPilihan(): array
    {
        $hasil = [];

        foreach ((array) $this->validated('ProdukContoh') as $baris) {
            if (is_array($baris) && is_string($baris['Nama'] ?? null) && is_string($baris['Harga'] ?? null)) {
                $hasil[] = ['Nama' => $baris['Nama'], 'Harga' => $baris['Harga']];
            }
        }

        return $hasil;
    }
}
