<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Model\BatchStok;
use Carbon\CarbonImmutable;

/**
 * Kueri publik domain Persediaan: identitas batch (nomor & kedaluwarsa) dari `IdBatchStok`, untuk domain lain yang
 * membalik mutasi keluar produk ber-batch (void & retur penjualan, F-05g). Membalik berarti **memasukkan kembali ke batch
 * yang sama**, dan mutasi masuk menyebut batch lewat nomor & kedaluwarsa (`DataBatchMasuk`), bukan lewat Id.
 */
final class InfoBatchStok
{
    /**
     * @param  list<int>  $idBatch
     * @return array<int, DataBatchMasuk> kunci = IdBatchStok
     */
    public function AmbilBanyak(array $idBatch): array
    {
        $idBatch = array_values(array_unique($idBatch));

        if ($idBatch === []) {
            return [];
        }

        $hasil = [];

        foreach (BatchStok::query()->whereIn('Id', $idBatch)->get(['Id', 'NomorBatch', 'TanggalKedaluwarsa']) as $b) {
            $hasil[$b->Id] = new DataBatchMasuk(
                $b->NomorBatch,
                $b->TanggalKedaluwarsa === null ? null : CarbonImmutable::parse($b->TanggalKedaluwarsa->toDateString()),
            );
        }

        return $hasil;
    }
}
