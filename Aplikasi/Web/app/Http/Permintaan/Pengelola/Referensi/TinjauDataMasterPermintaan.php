<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TinjauDataMasterPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Keputusan' => ['required', Rule::enum(KeputusanTinjauan::class)],
            'Catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function AmbilKeputusan(): KeputusanTinjauan
    {
        return KeputusanTinjauan::from($this->string('Keputusan')->toString());
    }

    public function AmbilCatatan(): ?string
    {
        return $this->filled('Catatan') ? trim($this->string('Catatan')->toString()) : null;
    }
}
