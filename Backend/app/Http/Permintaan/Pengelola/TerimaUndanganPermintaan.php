<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class TerimaUndanganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            'KataSandi' => ['required', 'string', 'max:255', Password::min(12)->letters()->numbers()],
            'KonfirmasiKataSandi' => ['required', 'same:KataSandi'],
        ];
    }
}
