<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\NomorSeri;

/**
 * Batch bersisa & nomor seri tersedia satu produk di satu lokasi stok (pilihan baris keluar dokumen F-05b, tipe FE
 * `PelacakanTersedia`), serta pemetaan Uuid batch/nomor seri ke Id untuk validasi form. Uuid tenant lain tidak
 * dikenal (scope `MilikTenant`).
 */
final class PelacakanTersedia
{
    /**
     * @return array{Batch: list<array{Uuid: string, NomorBatch: string, TanggalKedaluwarsa: string|null, JumlahSisa: string}>, Seri: list<array{Uuid: string, Nomor: string}>}
     */
    public function Ambil(int $idProduk, int $idGudang): array
    {
        return [
            'Batch' => array_values(BatchStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)->where('JumlahSisa', '>', 0)
                ->orderByRaw('`TanggalKedaluwarsa` IS NULL')->orderBy('TanggalKedaluwarsa')->orderBy('NomorBatch')->limit(500)->get()
                ->map(fn (BatchStok $b): array => ['Uuid' => $b->Uuid, 'NomorBatch' => $b->NomorBatch, 'TanggalKedaluwarsa' => $b->TanggalKedaluwarsa?->toDateString(), 'JumlahSisa' => $b->JumlahSisa])->all()),
            'Seri' => array_values(NomorSeri::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)->where('Status', StatusNomorSeri::Tersedia->value)
                ->orderBy('Nomor')->limit(2000)->get()
                ->map(fn (NomorSeri $s): array => ['Uuid' => $s->Uuid, 'Nomor' => $s->Nomor])->all()),
        ];
    }

    /**
     * @param  list<string>  $uuid
     * @return array<string, int> Uuid → Id batch
     */
    public function AmbilIdBatch(array $uuid): array
    {
        return $uuid === [] ? [] : array_map('intval', BatchStok::query()->whereIn('Uuid', array_values(array_unique($uuid)))->pluck('Id', 'Uuid')->all());
    }

    /**
     * @param  list<string>  $uuid
     * @return array<string, int> Uuid → Id nomor seri
     */
    public function AmbilIdSeri(array $uuid): array
    {
        return $uuid === [] ? [] : array_map('intval', NomorSeri::query()->whereIn('Uuid', array_values(array_unique($uuid)))->pluck('Id', 'Uuid')->all());
    }

    /**
     * @param  list<int>  $id
     * @return array<int, string> Id → Uuid batch
     */
    public function AmbilUuidBatch(array $id): array
    {
        return $id === [] ? [] : array_map('strval', BatchStok::query()->whereIn('Id', array_values(array_unique($id)))->pluck('Uuid', 'Id')->all());
    }

    /**
     * @param  list<int>  $id
     * @return array<int, string> Id → Uuid nomor seri
     */
    public function AmbilUuidSeri(array $id): array
    {
        return $id === [] ? [] : array_map('strval', NomorSeri::query()->whereIn('Id', array_values(array_unique($id)))->pluck('Uuid', 'Id')->all());
    }
}
