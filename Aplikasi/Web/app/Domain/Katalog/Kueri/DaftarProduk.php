<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Data\DataSaringProduk;
use App\Domain\Katalog\Enum\StatusProduk;
use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Layanan\PohonKategoriTenant;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Daftar produk back-office (F-03 E.2), 25 per halaman. Anak varian tidak tampil (ada di bawah induknya). `kata`
 * mencocokkan Nama/SKU (sebagian) atau barcode (persis); kategori termasuk sub-kategorinya.
 */
final class DaftarProduk
{
    public const PER_HALAMAN = 25;

    /**
     * @return LengthAwarePaginator<int, Produk>
     */
    public function Ambil(DataSaringProduk $saring, int $halaman = 1): LengthAwarePaginator
    {
        $kata = trim($saring->kata);
        $pola = '%'.addcslashes($kata, '%_\\').'%';

        return Produk::query()
            ->whereNull('IdInduk')
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nama', 'like', $pola)
                ->orWhere('Sku', 'like', $pola)
                ->orWhereIn('Id', ProdukBarcode::query()->where('Barcode', $kata)->select('IdProduk'))
                ->orWhereIn('Id', Produk::query()->whereIn('Id', ProdukBarcode::query()->where('Barcode', $kata)->select('IdProduk'))->whereNotNull('IdInduk')->select('IdInduk'))))
            ->when($saring->idKategori !== null, fn ($kueri) => $kueri->whereIn('IdKategori', PohonKategoriTenant::Muat()->AmbilIdDenganTurunan((int) $saring->idKategori)))
            ->when($saring->jenis !== null, fn ($kueri) => $kueri->where('Jenis', $saring->jenis?->value))
            ->when($saring->status === StatusProduk::Aktif, fn ($kueri) => $kueri->whereNull('DiarsipkanPada'))
            ->when($saring->status === StatusProduk::Diarsipkan, fn ($kueri) => $kueri->whereNotNull('DiarsipkanPada'))
            ->when($saring->urut === '-DiubahPada', fn ($kueri) => $kueri->orderByDesc('DiubahPada'))
            ->when($saring->urut === 'Sku', fn ($kueri) => $kueri->orderBy('Sku'))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $halaman))
            ->withQueryString();
    }

    /**
     * Baris daftar (tipe FE `BarisProduk`) tanpa N+1.
     *
     * @param  list<Produk>  $produk
     * @return list<array<string, mixed>>
     */
    public function Petakan(array $produk): array
    {
        $id = array_map(fn (Produk $p): int => $p->Id, $produk);
        $namaKategori = Kategori::query()->whereIn('Id', array_filter(array_map(fn (Produk $p): ?int => $p->IdKategori, $produk)))->pluck('Nama', 'Id');
        $jumlahVarian = Produk::query()->whereIn('IdInduk', $id)->selectRaw('IdInduk, count(*) as Jumlah')->groupBy('IdInduk')->pluck('Jumlah', 'IdInduk');
        $simbol = Satuan::query()->whereIn('Id', array_map(fn (Produk $p): int => $p->IdSatuanDasar, $produk))->pluck('Simbol', 'Id');
        $hargaDasar = $this->AmbilHargaDasar($id);

        return array_map(fn (Produk $p): array => [
            'Uuid' => $p->Uuid,
            'Nama' => $p->Nama,
            'Sku' => $p->Sku,
            'Jenis' => $p->Jenis->value,
            'LabelJenis' => $p->Jenis->AmbilLabel(),
            'NamaKategori' => $p->IdKategori === null ? null : $namaKategori->get($p->IdKategori),
            'Merek' => $p->Merek,
            'HargaDasar' => $hargaDasar[$p->Id] ?? null,
            'SimbolSatuan' => (string) ($simbol->get($p->IdSatuanDasar) ?? ''),
            'JumlahVarian' => (int) ($jumlahVarian->get($p->Id) ?? 0),
            'TampilDiPos' => $p->TampilDiPos,
            'Status' => $p->AmbilStatus()->value,
            'UrlGambarKecil' => PenyimpanGambarProduk::BuatUrl($p, 'kecil'),
        ], $produk);
    }

    /**
     * Harga dasar (`JumlahMinimum` terkecil, tanpa daftar harga) satuan jual bawaan per produk.
     *
     * @param  list<int>  $idProduk
     * @return array<int, string>
     */
    public function AmbilHargaDasar(array $idProduk): array
    {
        if ($idProduk === []) {
            return [];
        }

        $satuanJual = ProdukSatuan::query()->whereIn('IdProduk', $idProduk)->where('DefaultJual', true)->pluck('Id', 'IdProduk');
        $hasil = [];

        foreach (ProdukHarga::query()->whereIn('IdProdukSatuan', $satuanJual->values()->all())->whereNull('IdDaftarHarga')->orderBy('JumlahMinimum')->orderBy('Id')->get() as $harga) {
            $hasil[$harga->IdProduk] ??= $harga->Harga;
        }

        return $hasil;
    }
}
