<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * D-22: tambah anggota tim langsung dengan kata sandi awal yang diketik Super Admin.
 */
final class TambahAnggotaTimPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            'Email' => ['required', 'string', 'email', 'max:191'],
            'KataSandi' => ['required', 'string', 'max:255', Password::min(12)->letters()->numbers()],
            'KodePeran' => ['required', 'array', 'min:1'],
            'KodePeran.*' => ['required', 'string', 'distinct', 'exists:PeranPengelola,Kode'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Nama' => 'nama', 'KataSandi' => 'kata sandi awal', 'KodePeran' => 'peran'];
    }
}
