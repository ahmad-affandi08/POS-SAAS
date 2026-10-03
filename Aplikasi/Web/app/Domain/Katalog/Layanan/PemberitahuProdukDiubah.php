<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Bersama\Peristiwa\PeristiwaIntegrasi;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Support\Facades\DB;

/**
 * X7 §16.4 (v3.96): satu webhook `produk.diubah` per produk per transaksi, dari jalur mana pun yang mengubah data
 * yang tampil di `GET /api/v1/produk` (form produk, harga dasar, satuan, barcode, arsip/pulihkan, impor, varian).
 * Ditandai dari event model `Produk`/`ProdukSatuan`/`ProdukBarcode`/`ProdukHarga` dasar (+ pembaruan massal anak varian),
 * sehingga aksi bersarang tidak mengirim dobel dan simpan ulang tanpa perubahan tidak mengirim apa pun. Peristiwa
 * dikirim sekali setelah commit terluar dan batal bila transaksi di-rollback. Scoped per request/job.
 */
final class PemberitahuProdukDiubah
{
    /** @var array<int, true> */
    private array $tertunda = [];

    public function Tandai(int $idTenant, int $id, ?string $uuid = null): void
    {
        if (isset($this->tertunda[$id])) {
            return;
        }

        $uuid ??= Produk::query()->withTrashed()->whereKey($id)->value('Uuid');

        if (! is_string($uuid)) {
            return;
        }

        $this->tertunda[$id] = true;
        DB::afterRollBack(function () use ($id): void {
            unset($this->tertunda[$id]);
        });
        DB::afterCommit(function () use ($idTenant, $id, $uuid): void {
            unset($this->tertunda[$id]);
            PeristiwaIntegrasi::dispatch($idTenant, 'produk.diubah', $id, ['Uuid' => $uuid]);
        });
    }

    /**
     * Pembaruan massal (`->update()`) tidak memicu event model; dipakai untuk anak varian yang ikut diarsipkan,
     * dipulihkan, atau menerima nilai dari induk.
     *
     * @param  iterable<Produk>  $daftar
     */
    public function TandaiBanyak(iterable $daftar): void
    {
        foreach ($daftar as $produk) {
            $this->Tandai($produk->IdTenant, $produk->Id, $produk->Uuid);
        }
    }

    /**
     * Event model: hanya perubahan nyata yang ditandai (Eloquent memicu `saved` juga saat tidak ada yang berubah).
     */
    public static function TandaiDariModel(int $idTenant, int $idProduk): void
    {
        app(self::class)->Tandai($idTenant, $idProduk);
    }
}
