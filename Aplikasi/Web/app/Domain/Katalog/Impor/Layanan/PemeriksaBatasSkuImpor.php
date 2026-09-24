<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Enum\AksiBarisImpor;
use App\Domain\Katalog\Impor\Enum\StatusBarisImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;

/**
 * Rencana `BatasSku` impor (BR-P04.3/BR-02.1, DesainF03 C.6): pemakaian SKU saat ini + baris valid yang akan
 * membuat produk terhitung (bukan induk varian) dibandingkan dengan batas paket efektif. Dihitung saat pratinjau &
 * sebelum menerapkan (paket bisa berubah di antaranya); saat menerapkan, tiap produk baru tetap diperiksa
 * `PastikanBatasPaket` di Aksi Tim 1.
 */
final class PemeriksaBatasSkuImpor
{
    public function __construct(
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianSku $pemakaianSku,
    ) {}

    public function HitungTambahan(ImporProduk $impor): int
    {
        return ImporProdukBaris::query()
            ->where('IdImporProduk', $impor->Id)
            ->where('Status', StatusBarisImpor::Valid->value)
            ->where('Aksi', AksiBarisImpor::Buat->value)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`Data`, '$.JenisAkhir')) <> ?", [JenisProduk::IndukVarian->value])
            ->count();
    }

    /**
     * @return array{Tambahan: int, Batas: int|null, Terpakai: int}
     */
    public function AmbilRingkasan(ImporProduk $impor): array
    {
        $ringkasan = $this->batasPaket->AmbilRingkasan($impor->IdTenant, 'BatasSku', $this->pemakaianSku->Hitung());

        return ['Tambahan' => $this->HitungTambahan($impor), 'Batas' => $ringkasan['Batas'], 'Terpakai' => $ringkasan['Terpakai']];
    }

    /** Pesan blokir (kalimat BR-02.1) bila impor melewati batas SKU paket; null = masih muat. */
    public function Periksa(ImporProduk $impor): ?string
    {
        $ringkasan = $this->AmbilRingkasan($impor);

        if ($ringkasan['Batas'] === null || $ringkasan['Tambahan'] === 0) {
            return null;
        }

        $sisa = max(0, $ringkasan['Batas'] - $ringkasan['Terpakai']);

        if ($ringkasan['Tambahan'] <= $sisa) {
            return null;
        }

        return "Impor ini menambah {$ringkasan['Tambahan']} produk, sedangkan paket Anda mencakup maksimal {$ringkasan['Batas']} SKU produk "
            ."dan tersisa {$sisa}. Tingkatkan paket atau tambah add-on di menu Langganan, atau kurangi baris di berkas.";
    }
}
