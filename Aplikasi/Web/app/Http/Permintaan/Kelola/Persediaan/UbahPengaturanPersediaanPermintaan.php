<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\MetodeHpp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pengaturan persediaan (DesainF05a D): `MetodeHpp` (RataRata/Fifo) dan `StokBolehMinus` (boolean); F-05b
 * `BatasPersetujuanPenyesuaian` opsional (string desimal rupiah ≥ 0, tanpa titik ribuan; kosong = tidak diubah).
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
            'BatasPersetujuanPenyesuaian' => ['nullable', 'regex:/^\d{1,14}(\.\d{1,2})?$/'],
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
            'BatasPersetujuanPenyesuaian.*' => 'Batas persetujuan berupa angka rupiah tanpa titik ribuan, misal 500000.',
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

    public function AmbilBatasPersetujuanPenyesuaian(): ?Uang
    {
        $nilai = $this->validated('BatasPersetujuanPenyesuaian');

        return is_string($nilai) && $nilai !== '' ? Uang::Dari($nilai) : null;
    }
}
