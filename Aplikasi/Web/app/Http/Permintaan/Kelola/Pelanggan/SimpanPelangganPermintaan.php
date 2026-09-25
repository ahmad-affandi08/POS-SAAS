<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pelanggan;

use App\Domain\Pelanggan\Data\DataPelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Isian pelanggan back-office (F-16a). `Tag` = daftar teks (maks. 10, masing-masing ≤ 30 karakter). */
final class SimpanPelangganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            'NoHp' => ['required', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
            'Email' => ['nullable', 'email', 'max:150'],
            'TanggalLahir' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'Alamat' => ['nullable', 'string', 'max:500'],
            'Tag' => ['nullable', 'array', 'max:10'],
            'Tag.*' => ['string', 'max:30'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'SetujuPemasaran' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'NoHp.regex' => 'Nomor HP hanya angka, spasi, +, (, ), titik, atau tanda hubung.',
            'TanggalLahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'Tag.max' => 'Paling banyak 10 tag per pelanggan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Nama' => 'nama', 'NoHp' => 'nomor HP', 'Email' => 'email', 'TanggalLahir' => 'tanggal lahir', 'Tag.*' => 'tag'];
    }

    public function AmbilData(int $idPengguna): DataPelanggan
    {
        $tag = [];

        foreach ((array) ($this->validated('Tag') ?? []) as $t) {
            $rapi = trim((string) $t);

            if ($rapi !== '' && ! in_array(mb_strtolower($rapi), array_map('mb_strtolower', $tag), true)) {
                $tag[] = $rapi;
            }
        }

        $lahir = $this->validated('TanggalLahir');

        return new DataPelanggan(
            nama: trim((string) $this->validated('Nama')),
            noHp: (string) $this->validated('NoHp'),
            email: is_string($this->validated('Email')) ? $this->validated('Email') : null,
            tanggalLahir: is_string($lahir) && $lahir !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $lahir) ?: null : null,
            alamat: is_string($this->validated('Alamat')) ? $this->validated('Alamat') : null,
            tag: $tag,
            catatan: is_string($this->validated('Catatan')) ? $this->validated('Catatan') : null,
            setujuPemasaran: $this->boolean('SetujuPemasaran'),
            idPengguna: $idPengguna,
        );
    }
}
