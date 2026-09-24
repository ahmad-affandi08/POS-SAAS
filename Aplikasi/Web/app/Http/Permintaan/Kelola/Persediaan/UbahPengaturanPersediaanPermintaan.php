<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Persediaan\Enum\MetodeHpp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pengaturan persediaan (DesainF05a D): `MetodeHpp` (RataRata/Fifo) dan `StokBolehMinus` (boolean).
 */
final class UbahPengaturanPersediaanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'MetodeHpp' => ['required', 'string', Rule::enum(MetodeHpp::class)],
            'StokBolehMinus' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'MetodeHpp.required' => 'Pilih metode HPP.',
            'MetodeHpp.*' => 'Metode HPP tidak dikenal.',
            'StokBolehMinus.*' => 'Pilih apakah stok boleh minus.',
        ];
    }

    public function AmbilMetodeHpp(): MetodeHpp
    {
        return MetodeHpp::from((string) $this->validated('MetodeHpp'));
    }

    public function AmbilStokBolehMinus(): bool
    {
        return $this->boolean('StokBolehMinus');
    }
}
