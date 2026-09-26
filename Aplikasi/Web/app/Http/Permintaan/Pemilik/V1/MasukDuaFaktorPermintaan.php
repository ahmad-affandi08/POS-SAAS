<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pemilik\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /api/pemilik/v1/masuk/dua-faktor` (OWN-01, BR-00.8): kode TOTP 6 digit atau kode pemulihan. */
final class MasukDuaFaktorPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'TokenTantangan' => ['required', 'string', 'max:200'],
            'Kode' => ['required', 'string', 'max:32'],
            'NamaPerangkat' => ['required', 'string', 'max:100'],
        ];
    }
}
