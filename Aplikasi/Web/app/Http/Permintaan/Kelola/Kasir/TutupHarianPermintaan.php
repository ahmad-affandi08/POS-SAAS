<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Kasir;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Tutup harian F-15: outlet (Uuid), tanggal bisnis, dan konfirmasi mengabaikan peringatan. */
final class TutupHarianPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Outlet' => ['required', 'string', 'size:26'],
            'TanggalBisnis' => ['required', 'date_format:Y-m-d'],
            'AbaikanPeringatan' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Outlet.*' => 'Pilih outlet.',
            'TanggalBisnis.*' => 'Tanggal bisnis harus berformat tahun-bulan-tanggal.',
            'AbaikanPeringatan.*' => 'Konfirmasi peringatan tidak valid.',
        ];
    }

    public function AmbilTanggal(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('TanggalBisnis')->toString()) ?: CarbonImmutable::today();
    }
}
