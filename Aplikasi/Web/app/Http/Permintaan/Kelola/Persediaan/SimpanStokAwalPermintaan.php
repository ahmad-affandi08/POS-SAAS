<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Persediaan\Data\DataStokAwal;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Validasi form stok awal POST/PUT (DesainF05a D, Requests).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim C (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class SimpanStokAwalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        throw new LogicException('F-05a Tim C');
    }

    public function AmbilData(): DataStokAwal
    {
        throw new LogicException('F-05a Tim C');
    }
}
