<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\Satuan;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ringkasan produk tenant aktif untuk domain Persediaan (DesainF05a C.1): per Id/Uuid, pencarian produk berstok,
 * pencocokan kunci impor, dan jumlah produk berstok. Produk berstok = jenis `CekPunyaStok()` selain Konsinyasi
 * (Konsinyasi ditolak di stok awal sampai F-05i).
 */
final class InfoProdukStok
{
    private const UKURAN_POTONGAN = 1000;

    /**
     * @param  list<int>  $id
     * @return array<int, DataInfoProdukStok> kunci = Id
     */
    public function AmbilBanyak(array $id, bool $denganTerhapus = false): array
    {
        $hasil = [];

        foreach (array_chunk(array_values(array_unique($id)), self::UKURAN_POTONGAN) as $potongan) {
            $produk = Produk::query()
                ->when($denganTerhapus, fn ($kueri) => $kueri->withTrashed())
                ->whereIn('Id', $potongan)
                ->get();

            foreach ($this->Petakan($produk) as $data) {
                $hasil[$data->id] = $data;
            }
        }

        return $hasil;
    }

    /**
     * Produk yang belum dihapus, per Uuid.
     *
     * @param  list<string>  $uuid
     * @return array<string, DataInfoProdukStok> kunci = Uuid
     */
    public function AmbilDariUuid(array $uuid): array
    {
        $hasil = [];

        foreach (array_chunk(array_values(array_unique($uuid)), self::UKURAN_POTONGAN) as $potongan) {
            foreach ($this->Petakan(Produk::query()->whereIn('Uuid', $potongan)->get()) as $data) {
                $hasil[$data->uuid] = $data;
            }
        }

        return $hasil;
    }

    /**
     * Produk berstok yang belum diarsipkan dengan Nama/SKU mengandung `kata` atau barcode persis `kata`, urut nama.
     *
     * @return list<DataInfoProdukStok>
     */
    public function CariUntukStok(string $kata, int $batas = 20): array
    {
        $kata = trim($kata);
        $pola = '%'.addcslashes($kata, '%_\\').'%';

        $produk = Produk::query()
            ->whereNull('DiarsipkanPada')
            ->whereIn('Jenis', self::AmbilJenisBerstok())
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nama', 'like', $pola)
                ->orWhere('Sku', 'like', $pola)
                ->orWhereIn('Id', ProdukBarcode::query()->where('Barcode', $kata)->select('IdProduk'))))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->limit(max(1, min(50, $batas)))
            ->get();

        return $this->Petakan($produk);
    }

    /**
     * Pencocokan kolom produk impor: SKU (tanpa membedakan huruf besar/kecil), lalu barcode persis, lalu nama persis
     * (tanpa membedakan huruf besar/kecil). Spasi tepi diabaikan. Produk terhapus tidak ikut; produk diarsipkan ikut
     * (pemanggil menolaknya dengan pesan yang tepat). Lebih dari satu Id = ambigu; kosong = tidak dikenal.
     *
     * @param  list<string>  $kunci
     * @return array<string, list<int>> kunci = teks masukan apa adanya
     */
    public function CariKunciImpor(array $kunci): array
    {
        $normal = [];

        foreach ($kunci as $teks) {
            $bersih = trim($teks);

            if ($bersih !== '') {
                $normal[$bersih] = true;
            }
        }

        $daftar = array_keys($normal);
        $perSku = [];
        $perBarcode = [];
        $perNama = [];

        foreach (array_chunk(array_map('strval', $daftar), self::UKURAN_POTONGAN) as $potongan) {
            foreach (Produk::query()->whereIn('Sku', $potongan)->orderBy('Id')->get(['Id', 'Sku']) as $produk) {
                $perSku[mb_strtolower((string) $produk->Sku)][] = $produk->Id;
            }

            foreach (ProdukBarcode::query()->whereIn('Barcode', $potongan)->whereIn('IdProduk', Produk::query()->select('Id'))->orderBy('IdProduk')->get(['IdProduk', 'Barcode']) as $barcode) {
                $perBarcode[$barcode->Barcode][] = $barcode->IdProduk;
            }

            foreach (Produk::query()->whereIn('Nama', $potongan)->orderBy('Id')->get(['Id', 'Nama']) as $produk) {
                $perNama[mb_strtolower($produk->Nama)][] = $produk->Id;
            }
        }

        $hasil = [];

        foreach ($kunci as $teks) {
            $bersih = trim($teks);
            $kecil = mb_strtolower($bersih);
            $cocok = $bersih === '' ? [] : ($perSku[$kecil] ?? $perBarcode[$bersih] ?? $perNama[$kecil] ?? []);
            $hasil[$teks] = array_values(array_unique($cocok));
        }

        return $hasil;
    }

    /** Jumlah produk berstok yang belum diarsipkan (butir panduan awal "Isi stok awal"). */
    public function HitungBerstok(): int
    {
        return Produk::query()->whereNull('DiarsipkanPada')->whereIn('Jenis', self::AmbilJenisBerstok())->count();
    }

    /**
     * @return list<string>
     */
    private static function AmbilJenisBerstok(): array
    {
        return array_values(array_map(
            fn (JenisProduk $jenis): string => $jenis->value,
            array_filter(JenisProduk::cases(), fn (JenisProduk $jenis): bool => $jenis->CekPunyaStok() && $jenis !== JenisProduk::Konsinyasi),
        ));
    }

    /**
     * @param  Collection<int, Produk>  $produk
     * @return list<DataInfoProdukStok>
     */
    private function Petakan(Collection $produk): array
    {
        $idSatuan = array_values(array_unique($produk->pluck('IdSatuanDasar')->all()));
        $satuan = $idSatuan === [] ? collect() : Satuan::query()->whereIn('Id', $idSatuan)->get()->keyBy('Id');

        return array_values($produk->map(fn (Produk $p): DataInfoProdukStok => new DataInfoProdukStok(
            $p->Id,
            $p->Uuid,
            $p->Nama,
            $p->Sku,
            $p->Jenis,
            $p->Pelacakan,
            $p->BolehMinus,
            $p->IdSatuanDasar,
            (string) $satuan->get($p->IdSatuanDasar)?->Simbol,
            (bool) $satuan->get($p->IdSatuanDasar)?->BolehDesimal,
            $p->DiarsipkanPada !== null,
            $p->DihapusPada !== null,
        ))->all());
    }
}
