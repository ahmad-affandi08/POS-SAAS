<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Enum\GolonganObat;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;

/**
 * Kueri publik Katalog untuk laporan apotek (Sektor Apotek bagian 1, PRD §9.5): produk bergolongan obat tertentu
 * (termasuk yang diarsipkan/dihapus, karena mutasinya tetap harus dilaporkan), urut nama.
 */
final class ObatUntukLaporan
{
    /**
     * @param  list<GolonganObat>  $golongan
     * @return list<array{Id: int, Uuid: string, Nama: string, Sku: string|null, Golongan: GolonganObat, Prekursor: bool, SimbolSatuan: string}>
     */
    public function AmbilPerGolongan(array $golongan): array
    {
        if ($golongan === []) {
            return [];
        }

        $produk = Produk::query()->withTrashed()
            ->whereIn('GolonganObat', array_map(fn (GolonganObat $g): string => $g->value, $golongan))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->get(['Id', 'Uuid', 'Nama', 'Sku', 'GolonganObat', 'Prekursor', 'IdSatuanDasar']);
        $simbol = Satuan::query()->whereIn('Id', $produk->pluck('IdSatuanDasar')->unique()->values()->all())->pluck('Simbol', 'Id');
        $hasil = [];

        foreach ($produk as $p) {
            if ($p->GolonganObat === null) {
                continue;
            }

            $hasil[] = [
                'Id' => $p->Id,
                'Uuid' => $p->Uuid,
                'Nama' => $p->Nama,
                'Sku' => $p->Sku,
                'Golongan' => $p->GolonganObat,
                'Prekursor' => $p->Prekursor,
                'SimbolSatuan' => (string) $simbol->get($p->IdSatuanDasar, ''),
            ];
        }

        return $hasil;
    }
}
