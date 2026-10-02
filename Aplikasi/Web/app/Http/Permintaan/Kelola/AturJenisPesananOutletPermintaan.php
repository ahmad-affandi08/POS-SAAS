<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Enum\JenisPesanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jenis pesanan kasir outlet (v3.51): `{ Otomatis, JenisPesanan: string[], JenisPesananBawaan }`.
 */
final class AturJenisPesananOutletPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Otomatis' => ['required', 'boolean'],
            'JenisPesanan' => ['present', 'array', 'max:3'],
            'JenisPesanan.*' => ['required', 'string', 'distinct', Rule::enum(JenisPesanan::class)],
            'JenisPesananBawaan' => ['nullable', 'string', Rule::enum(JenisPesanan::class)],
        ];
    }

    /** @return list<JenisPesanan> */
    public function AmbilDaftar(): array
    {
        /** @var list<string> $nilai */
        $nilai = $this->validated('JenisPesanan');

        return array_values(array_map(fn (string $j): JenisPesanan => JenisPesanan::from($j), $nilai));
    }

    public function AmbilBawaan(): ?JenisPesanan
    {
        $nilai = $this->validated('JenisPesananBawaan');

        return is_string($nilai) ? JenisPesanan::from($nilai) : null;
    }
}
