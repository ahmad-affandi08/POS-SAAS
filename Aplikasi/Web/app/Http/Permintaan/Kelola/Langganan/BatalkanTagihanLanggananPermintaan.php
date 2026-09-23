<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Langganan;

use Illuminate\Foundation\Http\FormRequest;

final class BatalkanTagihanLanggananPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['Alasan' => ['nullable', 'string', 'max:500']];
    }

    public function AmbilAlasan(): string
    {
        return $this->filled('Alasan') ? $this->string('Alasan')->trim()->toString() : 'Dibatalkan pemilik usaha.';
    }
}
