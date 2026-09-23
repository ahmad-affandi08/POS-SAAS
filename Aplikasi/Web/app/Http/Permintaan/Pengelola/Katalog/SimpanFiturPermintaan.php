<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Data\DataFitur;
use Illuminate\Foundation\Http\FormRequest;

final class SimpanFiturPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Kunci' => ['required', 'string', 'max:100'],
            'Nama' => ['required', 'string', 'max:150'],
            'Modul' => ['required', 'string', 'max:50'],
            'Keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function AmbilData(): DataFitur
    {
        return new DataFitur(
            kunci: trim($this->string('Kunci')->toString()),
            nama: trim($this->string('Nama')->toString()),
            modul: trim($this->string('Modul')->toString()),
            keterangan: $this->filled('Keterangan') ? trim($this->string('Keterangan')->toString()) : null,
        );
    }
}
