<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pemilik\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `GET /api/pemilik/v1/shift?tanggal=&outlet=` (OWN-05). Tanpa tanggal = tanggal bisnis hari ini. */
final class ShiftPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'outlet' => ['nullable', 'string', 'max:26'],
        ];
    }
}
