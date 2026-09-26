<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Integrasi;

use Illuminate\Foundation\Http\FormRequest;

final class UbahIzinPenyediaGerbangPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Diizinkan' => ['required', 'boolean'],
            'Alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function AmbilAlasan(): ?string
    {
        return $this->filled('Alasan') ? trim($this->string('Alasan')->toString()) : null;
    }
}
