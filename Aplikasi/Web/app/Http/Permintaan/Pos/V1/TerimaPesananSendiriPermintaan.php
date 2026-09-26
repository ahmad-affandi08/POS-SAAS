<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 `POST /api/pos/v1/pesan-sendiri/{uuid}/terima`: `{UuidPengguna, UuidPesananTerbuka}` (pesanan terbuka yang
 * dibuat perangkat lewat outbox untuk pesanan ini).
 */
final class TerimaPesananSendiriPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPengguna' => ['required', 'ulid'],
            'UuidPesananTerbuka' => ['required', 'ulid'],
        ];
    }
}
