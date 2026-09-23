<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use Illuminate\Foundation\Http\FormRequest;

final class AjukanPembatalanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['Alasan' => ['required', 'string', 'max:500']];
    }
}
