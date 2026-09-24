<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;

/**
 * Baris MutasiStok satu dokumen sumber (tenant aktif) urut Id, opsional hanya KunciBaris berawalan tertentu (misal
 * `P/` = baris posting stok awal, tanpa baris pembatalan `B/`). Baris mutasi tidak pernah berubah, jadi tidak perlu
 * dikunci; pemanggil mengunci dokumen sumbernya (L2) dan saldo (L3).
 */
final class MutasiDokumen
{
    /**
     * @return list<MutasiStok>
     */
    public function Ambil(JenisReferensiMutasi $jenis, int $idReferensi, ?string $awalanKunci = null): array
    {
        return array_values(MutasiStok::query()
            ->where('JenisReferensi', $jenis->value)
            ->where('IdReferensi', $idReferensi)
            ->when($awalanKunci !== null && $awalanKunci !== '', fn ($kueri) => $kueri->where('KunciBaris', 'like', addcslashes((string) $awalanKunci, '%_\\').'%'))
            ->orderBy('Id')
            ->get()
            ->all());
    }
}
