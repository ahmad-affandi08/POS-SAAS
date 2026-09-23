<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\TemplateSektor;

use App\Domain\Pengelola\TemplateSektor\Data\DataTemplateSektor;
use Illuminate\Foundation\Http\FormRequest;

final class BuatTemplateSektorPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'regex:/^[A-Z]{3}-[A-Z]{3}$/'],
            'Nama' => ['required', 'string', 'max:100'],
            'Keterangan' => ['nullable', 'string', 'max:500'],
            'KodeTemplateDasar' => ['nullable', 'string', 'exists:TemplateSektor,Kode'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Kode.regex' => 'Kode berformat tiga huruf besar, tanda hubung, tiga huruf besar, misal FNB-RST.'];
    }

    public function AmbilData(): DataTemplateSektor
    {
        return new DataTemplateSektor(
            kode: $this->string('Kode')->toString(),
            nama: trim($this->string('Nama')->toString()),
            keterangan: $this->filled('Keterangan') ? trim($this->string('Keterangan')->toString()) : null,
            kodeTemplateDasar: $this->filled('KodeTemplateDasar') ? $this->string('KodeTemplateDasar')->toString() : null,
        );
    }
}
