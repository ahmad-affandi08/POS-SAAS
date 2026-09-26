<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use App\Domain\Penjualan\Enum\KanalPesanKeluar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * K3 `POST /api/pos/v1/penjualan/{uuidPenjualan}/kirim-struk`: `{Uuid, Kanal: Whatsapp|Email, Tujuan}`. Kesahihan
 * nomor/email diperiksa aksi (422 `TujuanTidakValid`).
 */
final class KirimStrukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Uuid' => ['required', 'string', 'ulid'],
            'Kanal' => ['required', 'string', Rule::enum(KanalPesanKeluar::class)],
            'Tujuan' => ['required', 'string', 'max:254'],
        ];
    }
}
