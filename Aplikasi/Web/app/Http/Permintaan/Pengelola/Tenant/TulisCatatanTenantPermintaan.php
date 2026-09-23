<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use Illuminate\Foundation\Http\FormRequest;

final class TulisCatatanTenantPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['Isi' => ['required', 'string', 'min:3', 'max:2000']];
    }

    public function AmbilIsi(): string
    {
        return trim(str_replace("\r\n", "\n", $this->string('Isi')->toString()));
    }
}
