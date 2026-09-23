<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\PanduanAwal;

use App\Domain\PanduanAwal\Kueri\TemplateTerbit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-01 langkah 2: template utama outlet + sektor tambahan (usaha campuran). Hanya kode template terbit yang dikenal;
 * template utama diperiksa ulang di Aksi (error `KodeTemplate`).
 */
final class TerapkanTemplateSektorPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'KodeTemplate' => ['required', 'string', 'max:20'],
            'SektorLain' => ['nullable', 'array', 'max:10'],
            'SektorLain.*' => ['string', 'max:20', Rule::in(app(TemplateTerbit::class)->AmbilKode())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'KodeTemplate.required' => 'Pilih template jenis usaha.',
            'SektorLain.max' => 'Pilih maksimal 10 jenis usaha tambahan.',
            'SektorLain.*.in' => 'Pilih jenis usaha tambahan dari daftar.',
        ];
    }

    /**
     * @return list<string>
     */
    public function AmbilSektorLain(): array
    {
        $daftar = $this->input('SektorLain');

        return is_array($daftar) ? array_values(array_filter($daftar, 'is_string')) : [];
    }
}
