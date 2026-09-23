<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use Illuminate\Foundation\Http\FormRequest;

final class NonaktifkanDuaFaktorPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'KataSandi' => ['required', 'string', 'max:100'],
        ];
    }
}
