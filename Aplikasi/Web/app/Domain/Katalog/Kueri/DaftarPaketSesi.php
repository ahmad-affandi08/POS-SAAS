<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\PaketSesi;
use App\Domain\Katalog\Model\PaketSesiProduk;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Support\Collection;

/**
 * Daftar definisi paket sesi (F-16d bagian 2) untuk `TabelData` back-office: cari nama produk, saring aktif, urut nama
 * & jumlah sesi. Juga bentuk formulir (satu paket) dan pilihan produk Jasa.
 */
final class DaftarPaketSesi
{
    public const KOLOM_URUT = ['Nama', 'JumlahSesi', 'MasaBerlakuHari'];

    public const KOLOM_SARING = ['Aktif'];

    public const URUT_BAWAAN = 'Nama';

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array<string, mixed>}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $aktif = $permintaan->AmbilDaftar('Aktif', ['Ya', 'Tidak']);
        $kueri = PaketSesi::query()
            ->join('Produk', 'Produk.Id', '=', 'PaketSesi.IdProduk')
            ->select('PaketSesi.*', 'Produk.Nama as NamaProduk')
            ->when(count($aktif) === 1, fn ($k) => $k->where('PaketSesi.Aktif', $aktif[0] === 'Ya'))
            ->when($permintaan->cari !== '', fn ($k) => $k->where('Produk.Nama', 'like', PenerapKueriTabel::PolaCari($permintaan->cari)));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, [
            'Nama' => 'Produk.Nama',
            'JumlahSesi' => 'PaketSesi.JumlahSesi',
            'MasaBerlakuHari' => 'PaketSesi.MasaBerlakuHari',
        ], function (Collection $baris): array {
            /** @var Collection<int, PaketSesi> $baris */
            $jumlahBerlaku = PaketSesiProduk::query()
                ->whereIn('IdPaketSesi', $baris->pluck('Id')->all())
                ->selectRaw('IdPaketSesi, COUNT(*) as Jumlah')
                ->groupBy('IdPaketSesi')
                ->pluck('Jumlah', 'IdPaketSesi')
                ->all();

            return array_values($baris->map(fn (PaketSesi $p): array => [
                'Uuid' => $p->Uuid,
                'Nama' => (string) $p->getAttribute('NamaProduk'),
                'JumlahSesi' => $p->JumlahSesi,
                'MasaBerlakuHari' => $p->MasaBerlakuHari,
                'SemuaProdukJasa' => $p->SemuaProdukJasa,
                'JumlahProdukBerlaku' => (int) ($jumlahBerlaku[$p->Id] ?? 0),
                'Aktif' => $p->Aktif,
            ])->all());
        });
    }

    /**
     * @return array{Uuid: string, UuidProduk: string, NamaProduk: string, JumlahSesi: int, MasaBerlakuHari: int|null, SemuaProdukJasa: bool, ProdukBerlaku: list<string>, Aktif: bool}
     */
    public function AmbilFormulir(PaketSesi $p): array
    {
        $p->loadMissing(['Produk:Id,Uuid,Nama', 'ProdukBerlaku.Produk:Id,Uuid']);

        return [
            'Uuid' => $p->Uuid,
            'UuidProduk' => $p->Produk->Uuid,
            'NamaProduk' => $p->Produk->Nama,
            'JumlahSesi' => $p->JumlahSesi,
            'MasaBerlakuHari' => $p->MasaBerlakuHari,
            'SemuaProdukJasa' => $p->SemuaProdukJasa,
            'ProdukBerlaku' => array_values($p->ProdukBerlaku->map(fn (PaketSesiProduk $b): string => $b->Produk->Uuid)->all()),
            'Aktif' => $p->Aktif,
        ];
    }

    /**
     * Produk Jasa aktif (pilihan formulir): Uuid, nama, dan apakah sudah punya definisi paket.
     *
     * @return list<array{Nilai: string, Label: string, SudahPaket: bool}>
     */
    public function AmbilPilihanJasa(): array
    {
        $sudah = PaketSesi::query()->pluck('IdProduk')->all();

        return array_values(Produk::query()
            ->where('Jenis', JenisProduk::Jasa->value)
            ->whereNull('DiarsipkanPada')
            ->orderBy('Nama')
            ->limit(1000)
            ->get(['Id', 'Uuid', 'Nama'])
            ->map(fn (Produk $p): array => ['Nilai' => $p->Uuid, 'Label' => $p->Nama, 'SudahPaket' => in_array($p->Id, $sudah, true)])
            ->all());
    }
}
