<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;

/**
 * Halaman `Kelola/Produk/Harga` (E.6): satuan produk dengan harga dasar & bertingkatnya, dan semua daftar harga
 * tenant (aktif dulu, prioritas tertinggi) beserta baris harga produk ini di tiap daftar.
 */
final class HargaProdukUntukHalaman
{
    public function __construct(private readonly DaftarDaftarHarga $daftarDaftarHarga) {}

    /**
     * @return array{Satuan: list<array<string, mixed>>, DaftarHarga: list<array<string, mixed>>}
     */
    public function Ambil(Produk $produk): array
    {
        $satuan = ProdukSatuan::query()->where('IdProduk', $produk->Id)->orderByDesc('DefaultJual')->orderBy('KonversiKeDasar')->orderBy('Id')->get();
        $unit = Satuan::query()->whereIn('Id', $satuan->pluck('IdSatuan')->all())->get()->keyBy('Id');
        $uuidSatuan = $satuan->pluck('Uuid', 'Id');
        $harga = ProdukHarga::query()->where('IdProduk', $produk->Id)->orderBy('JumlahMinimum')->get();
        $daftar = DaftarHarga::query()->orderByDesc('Aktif')->orderByDesc('Prioritas')->orderBy('Nama')->orderBy('Id')->get();
        $namaOutlet = $this->daftarDaftarHarga->AmbilNamaOutlet(array_values($daftar->all()));
        $zona = $this->daftarDaftarHarga->AmbilZonaWaktu();
        $baris = fn (ProdukHarga $h): array => ['JumlahMinimum' => $h->JumlahMinimum, 'Harga' => $h->Harga];

        return [
            'Satuan' => array_values($satuan->map(fn (ProdukSatuan $s): array => [
                'UuidProdukSatuan' => $s->Uuid,
                'Nama' => $unit->get($s->IdSatuan)->Nama ?? '',
                'Simbol' => $unit->get($s->IdSatuan)->Simbol ?? '',
                'KonversiKeDasar' => $s->KonversiKeDasar,
                'BolehDesimal' => $unit->get($s->IdSatuan)->BolehDesimal ?? false,
                'HargaDasar' => array_values($harga->filter(fn (ProdukHarga $h): bool => $h->IdProdukSatuan === $s->Id && $h->IdDaftarHarga === null)->map($baris)->all()),
            ])->all()),
            'DaftarHarga' => array_values($daftar->map(fn (DaftarHarga $d): array => [
                'Uuid' => $d->Uuid,
                'Nama' => $d->Nama,
                'Aktif' => $d->Aktif,
                'Ringkasan' => DaftarDaftarHarga::BuatRingkasan($d, $namaOutlet, $zona),
                'Harga' => array_values($harga
                    ->filter(fn (ProdukHarga $h): bool => $h->IdDaftarHarga === $d->Id)
                    ->map(fn (ProdukHarga $h): array => $baris($h) + ['UuidProdukSatuan' => (string) $uuidSatuan->get($h->IdProdukSatuan)])
                    ->all()),
            ])->all()),
        ];
    }
}
