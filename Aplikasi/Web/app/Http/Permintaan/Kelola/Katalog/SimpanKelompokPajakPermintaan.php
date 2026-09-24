<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Enum\KategoriPajakProduk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form kelompok pajak (E.8): `{ Nama, Kategori, Pajak: { KodeJenisPajak, DasarPengenaan }[] }`. Tanpa angka tarif:
 * tarif dicari dari `TarifPajak` bertanggal saat transaksi.
 */
final class SimpanKelompokPajakPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:60'],
            'Kategori' => ['required', Rule::enum(KategoriPajakProduk::class)],
            'Pajak' => ['present', 'array', 'max:10'],
            'Pajak.*.KodeJenisPajak' => ['required', 'string', 'max:40'],
            'Pajak.*.DasarPengenaan' => ['required', Rule::enum(DasarPengenaanPajak::class)],
        ];
    }

    public function AmbilNama(): string
    {
        return $this->string('Nama')->toString();
    }

    public function AmbilKategori(): KategoriPajakProduk
    {
        return KategoriPajakProduk::from($this->string('Kategori')->toString());
    }

    /**
     * @return list<array{KodeJenisPajak: string, DasarPengenaan: DasarPengenaanPajak}>
     */
    public function AmbilPajak(): array
    {
        return array_values(array_map(fn (array $pajak): array => [
            'KodeJenisPajak' => (string) $pajak['KodeJenisPajak'],
            'DasarPengenaan' => DasarPengenaanPajak::from((string) $pajak['DasarPengenaan']),
        ], array_filter((array) $this->input('Pajak', []), 'is_array')));
    }
}
