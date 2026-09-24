<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;

/**
 * Pemilih produk (F-03 D.2, bahan resep/pilihan/komponen paket): produk aktif tenant yang Nama/SKU-nya mengandung
 * `kata` atau barcodenya persis `kata`, disaring jenis, beserta satuannya (KonversiKeDasar string).
 */
final class CariProduk
{
    /**
     * @param  list<JenisProduk>  $jenis  kosong = semua jenis
     * @return list<array{Uuid: string, Nama: string, Sku: string|null, Jenis: string, UuidSatuanDasar: string, Satuan: list<array{Uuid: string, Nama: string, Simbol: string, BolehDesimal: bool, KonversiKeDasar: string}>}>
     */
    public function Cari(string $kata, array $jenis = [], int $batas = 20): array
    {
        $kata = trim($kata);
        $pola = '%'.addcslashes($kata, '%_\\').'%';
        $produk = Produk::query()
            ->whereNull('DiarsipkanPada')
            ->when($jenis !== [], fn ($kueri) => $kueri->whereIn('Jenis', array_map(fn (JenisProduk $j): string => $j->value, $jenis)))
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nama', 'like', $pola)
                ->orWhere('Sku', 'like', $pola)
                ->orWhereIn('Id', ProdukBarcode::query()->where('Barcode', $kata)->select('IdProduk'))))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->limit(max(1, min(50, $batas)))
            ->get();

        $satuanProduk = ProdukSatuan::query()->whereIn('IdProduk', $produk->modelKeys())->orderBy('KonversiKeDasar')->orderBy('Id')->get()->groupBy('IdProduk');
        $satuan = Satuan::query()->get()->keyBy('Id');

        return array_values($produk->map(fn (Produk $p): array => [
            'Uuid' => $p->Uuid,
            'Nama' => $p->Nama,
            'Sku' => $p->Sku,
            'Jenis' => $p->Jenis->value,
            'UuidSatuanDasar' => (string) $satuan->get($p->IdSatuanDasar)?->Uuid,
            'Satuan' => array_values(($satuanProduk->get($p->Id) ?? collect())
                ->filter(fn (ProdukSatuan $s): bool => $satuan->has($s->IdSatuan))
                ->map(fn (ProdukSatuan $s): array => [
                    'Uuid' => (string) $satuan->get($s->IdSatuan)?->Uuid,
                    'Nama' => (string) $satuan->get($s->IdSatuan)?->Nama,
                    'Simbol' => (string) $satuan->get($s->IdSatuan)?->Simbol,
                    'BolehDesimal' => (bool) $satuan->get($s->IdSatuan)?->BolehDesimal,
                    'KonversiKeDasar' => $s->KonversiKeDasar,
                ])->all()),
        ])->all());
    }
}
