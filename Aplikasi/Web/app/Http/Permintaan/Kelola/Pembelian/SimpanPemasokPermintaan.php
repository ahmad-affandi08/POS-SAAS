<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembelian;

use App\Domain\Pembelian\Data\DataPemasok;
use Illuminate\Foundation\Http\FormRequest;

/** Isian pemasok (F-04 fase 1). */
final class SimpanPemasokPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'Nama' => ['required', 'string', 'max:150'],
            'NamaKontak' => ['nullable', 'string', 'max:100'],
            'NoHp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() -]+$/'],
            'Email' => ['nullable', 'email', 'max:150'],
            'Alamat' => ['nullable', 'string', 'max:500'],
            'Npwp' => ['nullable', 'string', 'max:30'],
            'Pkp' => ['required', 'boolean'],
            'TerminHari' => ['required', 'integer', 'min:0', 'max:365'],
            'NamaBank' => ['nullable', 'string', 'max:100'],
            'NomorRekening' => ['nullable', 'string', 'max:50'],
            'AtasNamaRekening' => ['nullable', 'string', 'max:150'],
            'Catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Kode.regex' => 'Kode pemasok hanya huruf, angka, titik, garis bawah, atau tanda hubung.',
            'NoHp.regex' => 'Nomor HP hanya angka, spasi, +, (, ), atau tanda hubung.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Kode' => 'kode', 'Nama' => 'nama', 'TerminHari' => 'termin', 'Email' => 'email', 'Pkp' => 'status PKP'];
    }

    public function AmbilData(int $idPengguna): DataPemasok
    {
        return new DataPemasok(
            (string) $this->validated('Kode'),
            (string) $this->validated('Nama'),
            AturanPembelian::Teks($this->validated('NamaKontak')),
            AturanPembelian::Teks($this->validated('NoHp')),
            AturanPembelian::Teks($this->validated('Email')),
            AturanPembelian::Teks($this->validated('Alamat')),
            AturanPembelian::Teks($this->validated('Npwp')),
            $this->boolean('Pkp'),
            (int) $this->validated('TerminHari'),
            AturanPembelian::Teks($this->validated('NamaBank')),
            AturanPembelian::Teks($this->validated('NomorRekening')),
            AturanPembelian::Teks($this->validated('AtasNamaRekening')),
            AturanPembelian::Teks($this->validated('Catatan')),
            $idPengguna,
        );
    }
}
