<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pemilik\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/pemilik/v1/dasbor?tanggal=&outlet=` (OWN-02). Tanpa tanggal = tanggal bisnis hari ini; tanpa outlet = semua
 * outlet yang boleh dilihat.
 */
final class DasborPermintaan extends FormRequest
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
