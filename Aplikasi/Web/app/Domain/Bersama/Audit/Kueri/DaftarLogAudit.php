<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Audit\Kueri;

use App\Domain\Bersama\Audit\Model\LogAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Log audit tenant aktif, terbaru di atas, bisa disaring awalan/sebagian nama peristiwa (misal `outlet` atau
 * `sesi.masuk`). Scope `MilikTenant` memastikan hanya log tenant aktif yang terbaca.
 */
final class DaftarLogAudit
{
    public const PER_HALAMAN = 50;

    /**
     * @return LengthAwarePaginator<int, LogAudit>
     */
    public function Ambil(string $kata): LengthAwarePaginator
    {
        return LogAudit::query()
            ->when($kata !== '', fn ($kueri) => $kueri->where('Peristiwa', 'like', '%'.addcslashes($kata, '%_\\').'%'))
            ->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman')
            ->withQueryString();
    }
}
