<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use Illuminate\Foundation\Http\FormRequest;

final class LupaKataSandiPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Email' => ['required', 'string', 'email:rfc', 'max:191'],
        ];
    }
}
