<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

final class MasukPinPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPengguna' => ['required', 'string', 'size:26'],
            'Pin' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Pin.regex' => 'PIN harus 6 angka.'];
    }
}
