<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 BR-17.2 `POST /api/pos/v1/produk/{uuid}/habis`: `{UuidPengguna, Habis}`. Idempoten menurut keadaan akhir.
 */
final class UbahKetersediaanProdukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPengguna' => ['required', 'ulid'],
            'Habis' => ['required', 'boolean'],
        ];
    }
}
