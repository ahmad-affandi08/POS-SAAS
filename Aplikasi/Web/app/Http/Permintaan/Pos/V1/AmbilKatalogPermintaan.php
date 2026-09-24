<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/pos/v1/katalog?sejak={Kursor}` (F-03 D.3). Tanpa `sejak` = sinkron lengkap.
 */
final class AmbilKatalogPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sejak' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function AmbilKursor(): ?string
    {
        return $this->filled('sejak') ? $this->string('sejak')->toString() : null;
    }
}
