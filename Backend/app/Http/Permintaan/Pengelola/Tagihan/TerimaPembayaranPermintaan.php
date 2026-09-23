<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tagihan;

use Illuminate\Foundation\Http\FormRequest;

final class TerimaPembayaranPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'JumlahDiterima' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'Catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['JumlahDiterima' => 'jumlah diterima di rekening'];
    }

    public function AmbilJumlah(): string
    {
        return $this->string('JumlahDiterima')->toString();
    }

    public function AmbilCatatan(): ?string
    {
        return $this->filled('Catatan') ? $this->string('Catatan')->trim()->toString() : null;
    }
}
