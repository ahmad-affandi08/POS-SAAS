<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Katalog;

use App\Domain\Tenant\Enum\StatusPaket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UbahStatusPaketPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Status' => ['required', Rule::in([StatusPaket::Aktif->value, StatusPaket::Diarsipkan->value])],
            'Alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function AmbilStatus(): StatusPaket
    {
        return StatusPaket::from($this->string('Status')->toString());
    }

    public function AmbilAlasan(): ?string
    {
        return $this->filled('Alasan') ? trim($this->string('Alasan')->toString()) : null;
    }
}
