<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use App\Domain\Tenant\Enum\JenisOverride;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class BuatOverrideTenantPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Jenis' => ['required', 'string', Rule::in([JenisOverride::Batas->value, JenisOverride::Fitur->value])],
            'Kunci' => ['required', 'string', 'max:100'],
            'Nilai' => ['nullable', 'required_if:Jenis,Batas', 'integer', 'min:0'],
            'BerakhirPada' => ['required', 'date_format:Y-m-d'],
            'Alasan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nilai.required_if' => 'Isi angka batas yang berlaku selama override.',
            'Alasan.min' => 'Tulis alasan minimal 10 karakter agar tim lain paham keputusannya.',
        ];
    }

    public function AmbilJenis(): JenisOverride
    {
        return JenisOverride::from($this->string('Jenis')->toString());
    }

    public function AmbilKunci(): string
    {
        return trim($this->string('Kunci')->toString());
    }

    public function AmbilNilai(): ?int
    {
        return $this->filled('Nilai') ? $this->integer('Nilai') : null;
    }

    /** Override berlaku sampai akhir tanggal yang dipilih (WIB). */
    public function AmbilBerakhirPada(): Carbon
    {
        return Carbon::parse($this->string('BerakhirPada')->toString(), 'Asia/Jakarta')->endOfDay()->utc();
    }

    public function AmbilAlasan(): string
    {
        return trim($this->string('Alasan')->toString());
    }
}
