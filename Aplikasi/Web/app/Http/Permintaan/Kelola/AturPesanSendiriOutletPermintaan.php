<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 sakelar pesan sendiri outlet: `{ Aktif }`.
 */
final class AturPesanSendiriOutletPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['Aktif' => ['required', 'boolean']];
    }
}
