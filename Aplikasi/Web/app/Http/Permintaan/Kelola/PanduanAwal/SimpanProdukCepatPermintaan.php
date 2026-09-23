<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\PanduanAwal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-01 langkah 4(d): tambah produk cepat (nama, harga, kategori opsional = Uuid kategori tenant). Harga = string
 * desimal bertitik tanpa pemisah ribuan.
 */
final class SimpanProdukCepatPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Produk' => ['required', 'array', 'min:1', 'max:20'],
            'Produk.*' => ['array'],
            'Produk.*.Nama' => ['required', 'string', 'max:150'],
            'Produk.*.Harga' => ['required', 'string', 'regex:'.SimpanProdukContohPermintaan::POLA_HARGA],
            'Produk.*.Kategori' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Produk.required' => 'Isi minimal satu produk.',
            'Produk.*.Nama.required' => 'Isi nama produk.',
            'Produk.*.Harga.required' => 'Isi harga jual.',
            'Produk.*.Harga.regex' => 'Harga berupa angka tanpa titik ribuan, misal 15000.',
            'Produk.*.Kategori.size' => 'Pilih kategori dari daftar.',
        ];
    }

    /**
     * @return list<array{Nama: string, Harga: string, Kategori: string|null}>
     */
    public function AmbilProduk(): array
    {
        $hasil = [];

        foreach ((array) $this->validated('Produk') as $baris) {
            if (is_array($baris) && is_string($baris['Nama'] ?? null) && is_string($baris['Harga'] ?? null)) {
                $hasil[] = [
                    'Nama' => $baris['Nama'],
                    'Harga' => $baris['Harga'],
                    'Kategori' => is_string($baris['Kategori'] ?? null) ? $baris['Kategori'] : null,
                ];
            }
        }

        return $hasil;
    }
}
