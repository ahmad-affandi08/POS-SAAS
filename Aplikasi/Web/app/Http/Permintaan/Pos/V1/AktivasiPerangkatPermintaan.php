<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use App\Domain\Organisasi\Data\DataAktivasiPerangkat;
use App\Domain\Organisasi\Enum\PlatformPerangkat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AktivasiPerangkatPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'max:20'],
            'Platform' => ['required', Rule::enum(PlatformPerangkat::class)],
            'VersiAplikasi' => ['nullable', 'string', 'max:30'],
            'VersiOs' => ['nullable', 'string', 'max:50'],
            'VersiSkemaSinkron' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function AmbilData(): DataAktivasiPerangkat
    {
        $versiHeader = $this->header('X-Versi-Aplikasi');

        return new DataAktivasiPerangkat(
            kode: $this->string('Kode')->toString(),
            platform: PlatformPerangkat::from($this->string('Platform')->toString()),
            versiAplikasi: $this->filled('VersiAplikasi') ? $this->string('VersiAplikasi')->toString() : (is_string($versiHeader) && $versiHeader !== '' ? mb_substr($versiHeader, 0, 30) : null),
            versiOs: $this->filled('VersiOs') ? $this->string('VersiOs')->toString() : null,
            versiSkemaSinkron: $this->filled('VersiSkemaSinkron') ? $this->string('VersiSkemaSinkron')->toString() : null,
        );
    }
}
