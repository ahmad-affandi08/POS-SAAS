<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;

/**
 * Kode Coretax per produk untuk baris Faktur Pajak (PRD v3.11–3.12): kode barang/jasa 6 digit, kode satuan `UM.00xx`
 * (null = belum diatur, pemanggil memakai kode umum dan memberi peringatan), dan apakah produknya jasa (Opt `B`).
 * Produk yang sudah diarsipkan/dihapus tetap terbaca karena faktur lama merujuknya.
 */
final class KodeCoretaxProduk
{
    /**
     * @param  list<int>  $idProduk
     * @return array<int, array{Kode: string|null, Unit: string|null, Jasa: bool}>
     */
    public function AmbilBanyak(array $idProduk): array
    {
        $idProduk = array_values(array_unique($idProduk));

        if ($idProduk === []) {
            return [];
        }

        $hasil = [];

        foreach (Produk::query()->withTrashed()->whereIn('Id', $idProduk)->get(['Id', 'Jenis', 'KodeBarangJasaCoretax', 'KodeUnitCoretax']) as $p) {
            $hasil[$p->Id] = [
                'Kode' => $p->KodeBarangJasaCoretax,
                'Unit' => $p->KodeUnitCoretax,
                'Jasa' => $p->Jenis === JenisProduk::Jasa,
            ];
        }

        return $hasil;
    }
}
