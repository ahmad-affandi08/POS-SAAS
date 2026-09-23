<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use App\Domain\Tenant\Enum\PenandaTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UbahPenandaTenantPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Penanda' => ['nullable', 'string', Rule::enum(PenandaTenant::class)],
            'Alasan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Alasan.min' => 'Tulis alasan minimal 10 karakter agar tim lain paham keputusannya.'];
    }

    public function AmbilPenanda(): ?PenandaTenant
    {
        return $this->filled('Penanda') ? PenandaTenant::from($this->string('Penanda')->toString()) : null;
    }

    public function AmbilAlasan(): string
    {
        return trim($this->string('Alasan')->toString());
    }
}
