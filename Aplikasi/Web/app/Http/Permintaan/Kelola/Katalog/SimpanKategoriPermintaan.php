<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Data\DataKategori;
use App\Domain\Katalog\Model\Kategori;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Form kategori F-03 (E.5): `{ Nama, UuidInduk, Urutan }`.
 */
final class SimpanKategoriPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:60'],
            'UuidInduk' => ['nullable', 'ulid'],
            'Urutan' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function AmbilData(): DataKategori
    {
        $idInduk = null;

        if ($this->filled('UuidInduk')) {
            $idInduk = Kategori::query()->where('Uuid', $this->string('UuidInduk')->toString())->value('Id');

            if (! is_int($idInduk)) {
                throw ValidationException::withMessages(['UuidInduk' => 'Kategori induk tidak ditemukan.']);
            }
        }

        return new DataKategori($this->string('Nama')->toString(), $idInduk, $this->integer('Urutan'));
    }
}
