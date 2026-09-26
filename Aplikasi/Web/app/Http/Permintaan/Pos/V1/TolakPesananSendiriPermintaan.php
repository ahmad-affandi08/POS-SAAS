<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 `POST /api/pos/v1/pesan-sendiri/{uuid}/tolak`: `{UuidPengguna, Alasan}` (3–200 karakter, tampil ke tamu).
 */
final class TolakPesananSendiriPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPengguna' => ['required', 'ulid'],
            'Alasan' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }
}
