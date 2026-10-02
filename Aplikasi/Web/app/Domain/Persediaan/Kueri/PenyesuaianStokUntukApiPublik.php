<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStokDetail;

/**
 * X7 bagian 4 (PRD §16.1 cakupan `stok:tulis`): penyesuaian stok dalam bentuk API publik (Uuid saja, jumlah string
 * desimal bertanda, tanpa HPP/nilai), dan pencarian batch dari nomornya untuk baris keluar yang dikirim pihak ketiga.
 */
final class PenyesuaianStokUntukApiPublik
{
    public function __construct(
        private readonly InfoGudang $gudang,
        private readonly InfoProdukStok $produk,
    ) {}

    /** @return array<string, mixed>|null */
    public function Satu(string $uuid): ?array
    {
        $p = PenyesuaianStok::query()->where('Uuid', $uuid)->first();

        if ($p === null) {
            return null;
        }

        $detail = PenyesuaianStokDetail::query()->where('IdPenyesuaianStok', $p->Id)->orderBy('Urutan')->get();
        $produk = $this->produk->AmbilBanyak(array_values(array_unique(array_map('intval', $detail->pluck('IdProduk')->all()))), denganTerhapus: true);

        return [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'Status' => $p->Status->value,
            'UuidGudang' => ($this->gudang->AmbilBanyak([$p->IdGudang])[$p->IdGudang] ?? null)?->uuid,
            'TanggalBisnis' => $p->Tanggal->toDateString(),
            'Alasan' => $p->KodeAlasan->value,
            'Keterangan' => $p->Keterangan,
            'Baris' => array_values($detail->map(fn (PenyesuaianStokDetail $d): array => [
                'UuidProduk' => ($produk[$d->IdProduk] ?? null)?->uuid,
                'Jumlah' => Kuantitas::Dari($d->Jumlah)->KeString(),
                'NomorBatch' => $d->NomorBatch,
            ])->all()),
        ];
    }

    /** Id batch bersisa produk di lokasi stok dari nomornya (tanpa membedakan huruf besar/kecil); null bila tidak ada. */
    public function CariIdBatch(int $idProduk, int $idGudang, string $nomorBatch): ?int
    {
        $id = BatchStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)
            ->whereRaw('UPPER(`NomorBatch`) = ?', [mb_strtoupper(trim($nomorBatch))])->value('Id');

        return $id === null ? null : (int) $id;
    }
}
