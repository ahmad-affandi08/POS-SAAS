<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;

final class MasukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Email' => ['required', 'string', 'email', 'max:191'],
            'KataSandi' => ['required', 'string', 'max:255'],
        ];
    }
}
