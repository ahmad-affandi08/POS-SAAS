<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Data\DataAreaMeja;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form area meja F-10a: `{ Nama, Urutan? }`.
 */
final class SimpanAreaMejaPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:60'],
            'Urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function AmbilData(): DataAreaMeja
    {
        return new DataAreaMeja($this->string('Nama')->toString(), $this->integer('Urutan'));
    }
}
