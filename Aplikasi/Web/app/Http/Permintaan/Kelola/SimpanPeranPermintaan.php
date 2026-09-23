<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use Illuminate\Foundation\Http\FormRequest;

final class SimpanPeranPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:100'],
            'Keterangan' => ['nullable', 'string', 'max:255'],
            'Izin' => ['required', 'array', 'min:1'],
            'Izin.*' => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Izin.required' => 'Pilih minimal satu izin.', 'Izin.min' => 'Pilih minimal satu izin.'];
    }

    /**
     * @return list<string>
     */
    public function AmbilIzin(): array
    {
        return array_values(array_map('strval', $this->array('Izin')));
    }
}
