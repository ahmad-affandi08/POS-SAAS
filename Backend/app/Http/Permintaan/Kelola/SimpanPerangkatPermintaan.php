<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Enum\JenisPerangkat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah perangkat (F-02b): `Outlet` = Uuid outlet, dicari ulang di kontroler dalam scope tenant & akses pelaku.
 * Saat mengubah, hanya `Nama` yang dipakai.
 */
final class SimpanPerangkatPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $baru = $this->isMethod('post');

        return [
            'Nama' => ['required', 'string', 'max:100'],
            'Outlet' => $baru ? ['required', 'string', 'size:26'] : ['nullable'],
            'Jenis' => $baru ? ['required', Rule::enum(JenisPerangkat::class)] : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nama.required' => 'Isi nama perangkat, misal "Kasir Depan".',
            'Outlet.required' => 'Pilih outlet perangkat ini.',
            'Outlet.size' => 'Pilih outlet dari daftar.',
        ];
    }
}
