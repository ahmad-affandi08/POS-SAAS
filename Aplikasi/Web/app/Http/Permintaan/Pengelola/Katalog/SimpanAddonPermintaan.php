<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Data\DataAddon;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanAddonPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $aturan = [
            'Kode' => ['required', 'string', 'regex:/^[A-Z0-9_]{2,30}$/'],
            'Nama' => ['required', 'string', 'max:100'],
            'HargaBulanan' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'KunciFitur' => ['nullable', 'string', 'exists:Fitur,Kunci'],
            'TambahanBatas' => ['array'],
            'Status' => ['required', Rule::in([StatusPaket::Aktif->value, StatusPaket::Diarsipkan->value])],
        ];

        foreach (Paket::KOLOM_BATAS as $kolom) {
            $aturan["TambahanBatas.{$kolom}"] = ['nullable', 'integer', 'min:1'];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Kode.regex' => 'Kode huruf besar, angka, atau garis bawah, misal OUTLET_TAMBAHAN.',
            'HargaBulanan.regex' => 'Harga ditulis tanpa titik ribuan, misal 50000.',
        ];
    }

    public function AmbilData(): DataAddon
    {
        $tambahan = [];

        foreach (Paket::KOLOM_BATAS as $kolom) {
            $nilai = $this->input("TambahanBatas.{$kolom}");

            if ($nilai !== null && $nilai !== '') {
                $tambahan[$kolom] = (int) $nilai;
            }
        }

        return new DataAddon(
            kode: $this->string('Kode')->toString(),
            nama: trim($this->string('Nama')->toString()),
            hargaBulanan: $this->string('HargaBulanan')->toString(),
            kunciFitur: $this->filled('KunciFitur') ? $this->string('KunciFitur')->toString() : null,
            tambahanBatas: $tambahan,
            status: StatusPaket::from($this->string('Status')->toString()),
        );
    }
}
