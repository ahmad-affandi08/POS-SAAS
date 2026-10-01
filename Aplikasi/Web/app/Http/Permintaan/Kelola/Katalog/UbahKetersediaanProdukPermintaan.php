<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 BR-17.2 `POST /kelola/produk/{produk}/habis` (back-office): `{UuidOutlet, Habis}`. Izin dijaga rute.
 */
final class UbahKetersediaanProdukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidOutlet' => ['required', 'ulid'],
            'Habis' => ['required', 'boolean'],
        ];
    }
}
