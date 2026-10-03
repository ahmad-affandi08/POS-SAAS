<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Aksi\UbahProdukMassal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Audit kemudahan pakai #19 `POST /kelola/produk/massal`: `{Aksi, Uuid[], UuidKategori?}`. Izin dijaga rute.
 */
final class UbahProdukMassalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Aksi' => ['required', 'string', Rule::in(UbahProdukMassal::AKSI)],
            'Uuid' => ['required', 'array', 'min:1', 'max:'.UbahProdukMassal::MAKS],
            'Uuid.*' => ['required', 'ulid'],
            'UuidKategori' => ['nullable', 'ulid', 'required_if:Aksi,Kategori'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Uuid' => 'produk terpilih', 'UuidKategori' => 'kategori'];
    }
}
