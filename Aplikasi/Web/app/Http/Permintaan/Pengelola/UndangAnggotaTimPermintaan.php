<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;

final class UndangAnggotaTimPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Email' => ['required', 'string', 'email', 'max:191'],
            'KodePeran' => ['required', 'array', 'min:1'],
            'KodePeran.*' => ['required', 'string', 'distinct', 'exists:PeranPengelola,Kode'],
        ];
    }
}
