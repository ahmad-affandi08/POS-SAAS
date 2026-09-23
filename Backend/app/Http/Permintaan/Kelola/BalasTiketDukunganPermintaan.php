<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Balasan pengguna tenant pada tiket dukungan (P-09).
 */
final class BalasTiketDukunganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Isi' => ['required', 'string', 'min:2', 'max:10000'],
            ...BuatTiketDukunganPermintaan::AturanLampiran(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Lampiran.*' => 'lampiran'];
    }

    public function AmbilIsi(): string
    {
        return str_replace("\r\n", "\n", $this->string('Isi')->trim()->toString());
    }

    /**
     * @return list<UploadedFile>
     */
    public function AmbilLampiran(): array
    {
        return BuatTiketDukunganPermintaan::AmbilBerkas($this);
    }
}
