<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Dukungan;

use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Perubahan status tiket oleh tim internal (P-09). Menutup tiket wajib alasan.
 */
final class UbahStatusTiketDukunganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Status' => ['required', 'string', Rule::enum(StatusTiketDukungan::class)->except([StatusTiketDukungan::Baru])],
            'Alasan' => [Rule::requiredIf($this->input('Status') === StatusTiketDukungan::Ditutup->value), 'nullable', 'string', 'max:500'],
        ];
    }

    public function AmbilStatus(): StatusTiketDukungan
    {
        return StatusTiketDukungan::from($this->string('Status')->toString());
    }

    public function AmbilAlasan(): ?string
    {
        return $this->filled('Alasan') ? $this->string('Alasan')->trim()->toString() : null;
    }
}
