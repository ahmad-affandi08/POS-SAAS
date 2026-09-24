<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;

/**
 * BR-03.2 dari sisi persediaan (DesainF05a C.8), ditandai `PemeriksaPemakaianProduk::TAG` di `PenyediaPersediaan`:
 * produk yang sudah punya riwayat stok (`MutasiStok`, termasuk yang dibatalkan) atau masih ada di stok awal Draf/
 * Memproses tidak bisa dihapus, diubah jenisnya, atau diganti satuan dasarnya.
 */
final class PemakaianProdukDiPersediaan implements PemeriksaPemakaianProduk
{
    public function PeriksaPemakaian(int $idProduk): ?string
    {
        if (MutasiStok::query()->where('IdProduk', $idProduk)->exists()) {
            return 'sudah punya riwayat stok';
        }

        $diDraf = StokAwalDetail::query()
            ->where('IdProduk', $idProduk)
            ->whereIn('IdStokAwal', StokAwal::query()
                ->whereIn('Status', [StatusStokAwal::Draf->value, StatusStokAwal::Memproses->value])
                ->select('Id'))
            ->exists();

        return $diDraf ? 'masih ada di draf stok awal' : null;
    }
}
