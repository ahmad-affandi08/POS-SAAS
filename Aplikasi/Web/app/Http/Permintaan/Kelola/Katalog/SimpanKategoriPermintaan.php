<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Data\DataKategori;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Organisasi\Kueri\DaftarStasiunDapur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Form kategori F-03 (E.5): `{ Nama, UuidInduk, Urutan, UuidStasiunDapur? }` (stasiun F-10a; kosong = stasiun bawaan,
 * tidak dikirim = tidak diubah).
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
            'UuidStasiunDapur' => ['nullable', 'ulid'],
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

        $idStasiun = null;

        if ($this->filled('UuidStasiunDapur')) {
            $idStasiun = app(DaftarStasiunDapur::class)->CariIdAktif($this->string('UuidStasiunDapur')->toString());

            if ($idStasiun === null) {
                throw ValidationException::withMessages(['UuidStasiunDapur' => 'Pilih stasiun dapur yang aktif.']);
            }
        }

        return new DataKategori($this->string('Nama')->toString(), $idInduk, $this->integer('Urutan'), $idStasiun, $this->has('UuidStasiunDapur'));
    }
}
