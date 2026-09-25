<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Data\DataMeja;
use App\Domain\Organisasi\Enum\BentukMeja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form meja F-10a: `{ Nama, Area?, Kapasitas, Bentuk, Urutan? }`. `Area` = Uuid area meja outlet yang sama.
 */
final class SimpanMejaPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:30'],
            'Area' => ['nullable', 'ulid'],
            'Kapasitas' => ['required', 'integer', 'min:1', 'max:99'],
            'Bentuk' => ['required', Rule::enum(BentukMeja::class)],
            'Urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Kapasitas.min' => 'Kapasitas minimal 1 orang.', 'Kapasitas.max' => 'Kapasitas maksimal 99 orang.'];
    }

    public function AmbilData(): DataMeja
    {
        return new DataMeja(
            nama: $this->string('Nama')->toString(),
            uuidArea: $this->filled('Area') ? $this->string('Area')->toString() : null,
            kapasitas: $this->integer('Kapasitas'),
            bentuk: BentukMeja::from($this->string('Bentuk')->toString()),
            urutan: $this->integer('Urutan'),
        );
    }
}
