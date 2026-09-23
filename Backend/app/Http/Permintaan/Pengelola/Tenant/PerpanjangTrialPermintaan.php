<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use App\Domain\Pengelola\Tenant\Aksi\PerpanjangTrial;
use Illuminate\Foundation\Http\FormRequest;

final class PerpanjangTrialPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Hari' => ['required', 'integer', 'min:1', 'max:'.PerpanjangTrial::MAKS_HARI],
            'Alasan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Hari.max' => 'Perpanjangan trial paling lama '.PerpanjangTrial::MAKS_HARI.' hari.',
            'Alasan.min' => 'Tulis alasan minimal 10 karakter agar tim lain paham keputusannya.',
        ];
    }

    public function AmbilHari(): int
    {
        return $this->integer('Hari');
    }

    public function AmbilAlasan(): string
    {
        return trim($this->string('Alasan')->toString());
    }
}
