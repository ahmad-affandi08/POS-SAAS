<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;

final class TetapkanPeranPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'KodePeran' => ['required', 'array', 'min:1'],
            'KodePeran.*' => ['required', 'string', 'distinct', 'exists:PeranPengelola,Kode'],
            'Alasan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
