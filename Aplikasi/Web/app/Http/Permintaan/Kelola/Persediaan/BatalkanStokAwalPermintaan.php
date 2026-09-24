<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Validasi alasan pembatalan stok awal (5–255 karakter, DesainF05a D).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class BatalkanStokAwalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        throw new LogicException('F-05a Tim C');
    }
}
