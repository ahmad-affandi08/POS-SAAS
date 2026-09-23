<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use App\Domain\Pengelola\Tenant\Enum\KategoriPenangguhan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TangguhkanTenantPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kategori' => ['required', 'string', Rule::enum(KategoriPenangguhan::class)],
            'Catatan' => ['required', 'string', 'min:10', 'max:450'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Catatan.min' => 'Tulis catatan minimal 10 karakter: apa yang terjadi dan dasar keputusannya.'];
    }

    public function AmbilKategori(): KategoriPenangguhan
    {
        return KategoriPenangguhan::from($this->string('Kategori')->toString());
    }

    public function AmbilCatatan(): string
    {
        return trim($this->string('Catatan')->toString());
    }
}
