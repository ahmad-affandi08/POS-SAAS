<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pemilik\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /api/pemilik/v1/masuk` (OWN-01). */
final class MasukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Email' => ['required', 'string', 'email', 'max:191'],
            'KataSandi' => ['required', 'string', 'max:100'],
            'NamaPerangkat' => ['required', 'string', 'max:100'],
        ];
    }
}
