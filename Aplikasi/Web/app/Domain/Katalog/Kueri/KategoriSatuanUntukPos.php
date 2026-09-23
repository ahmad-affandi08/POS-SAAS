<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Data\KonteksKatalogPos;
use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Satuan;

/**
 * Bagian katalog POS Tim 1 (F-03 D.3): `Kategori` dan `Satuan`. Lengkap = semua baris; delta = `DiubahPada ≥ sejak`
 * (baris yang dihapus dikirim lewat `Terhapus` dari `PenghapusanKatalog`).
 */
final class KategoriSatuanUntukPos implements BagianKatalogPos
{
    public function AmbilBagian(KonteksKatalogPos $konteks): array
    {
        $uuidKategori = Kategori::query()->pluck('Uuid', 'Id');
        $kategori = Kategori::query()
            ->when($konteks->sejak !== null, fn ($kueri) => $kueri->where('DiubahPada', '>=', $konteks->sejak))
            ->orderBy('Id')
            ->get();
        $satuan = Satuan::query()
            ->when($konteks->sejak !== null, fn ($kueri) => $kueri->where('DiubahPada', '>=', $konteks->sejak))
            ->orderBy('Id')
            ->get();

        return [
            'Kategori' => array_values($kategori->map(fn (Kategori $k): array => [
                'Uuid' => $k->Uuid,
                'UuidInduk' => $k->IdInduk === null ? null : $uuidKategori->get($k->IdInduk),
                'Nama' => $k->Nama,
                'Urutan' => $k->Urutan,
            ])->all()),
            'Satuan' => array_values($satuan->map(fn (Satuan $s): array => [
                'Uuid' => $s->Uuid,
                'Nama' => $s->Nama,
                'Simbol' => $s->Simbol,
                'BolehDesimal' => $s->BolehDesimal,
            ])->all()),
        ];
    }
}
