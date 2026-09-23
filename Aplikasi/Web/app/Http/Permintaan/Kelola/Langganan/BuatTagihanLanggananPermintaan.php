<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Langganan;

use App\Domain\Tenant\Enum\SiklusTagihan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BuatTagihanLanggananPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'KodePaket' => ['required', 'string', 'max:30'],
            'Siklus' => ['required', Rule::enum(SiklusTagihan::class)],
            'KodeKupon' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function AmbilSiklus(): SiklusTagihan
    {
        return SiklusTagihan::from($this->string('Siklus')->toString());
    }

    public function AmbilKodeKupon(): ?string
    {
        return $this->filled('KodeKupon') ? $this->string('KodeKupon')->trim()->upper()->toString() : null;
    }
}
