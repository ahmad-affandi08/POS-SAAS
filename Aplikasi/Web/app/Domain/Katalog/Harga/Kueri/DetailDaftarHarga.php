<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Harga\Layanan\WaktuLokalDaftarHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;

/**
 * Halaman `Kelola/DaftarHarga/Detail` (E.7): pengaturan daftar (format form, waktu di zona tenant) dan baris satuan
 * produk yang bisa dijual beserta harga dasarnya dan harga di daftar ini. Satuan yang sudah punya harga di daftar
 * tampil dulu; `kata` mencari nama/SKU produk. 25 per halaman.
 */
final class DetailDaftarHarga
{
    public const PER_HALAMAN = 25;

    public function __construct(
        private readonly PetaUuidOutlet $petaOutlet,
        private readonly DaftarDaftarHarga $daftarDaftarHarga,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @return array{Nama: string, UuidOutlet: list<string>, Kanal: string, TierPelanggan: string, MulaiPada: string, SelesaiPada: string, Prioritas: string, Uuid: string, Aktif: bool}
     */
    public function AmbilForm(DaftarHarga $daftar): array
    {
        $zona = $this->daftarDaftarHarga->AmbilZonaWaktu();

        return [
            'Uuid' => $daftar->Uuid,
            'Aktif' => $daftar->Aktif,
            'Nama' => $daftar->Nama,
            'UuidOutlet' => $daftar->IdOutlet === null ? [] : array_values($this->petaOutlet->Ambil($daftar->IdOutlet)),
            'Kanal' => $daftar->Kanal->value ?? '',
            'TierPelanggan' => $daftar->TierPelanggan ?? '',
            'MulaiPada' => WaktuLokalDaftarHarga::KeTeks($daftar->MulaiPada, $zona),
            'SelesaiPada' => WaktuLokalDaftarHarga::KeTeks($daftar->SelesaiPada, $zona),
            'Prioritas' => (string) $daftar->Prioritas,
        ];
    }

    /**
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function AmbilBaris(DaftarHarga $daftar, string $kata, int $halaman = 1): array
    {
        $kata = trim($kata);
        $pola = '%'.addcslashes($kata, '%_\\').'%';
        $jenisDijual = array_values(array_map(
            fn (JenisProduk $jenis): string => $jenis->value,
            array_filter(JenisProduk::cases(), fn (JenisProduk $jenis): bool => $jenis->CekBisaDijual()),
        ));
        // Pertahanan berlapis: join & subkueri ikut dibatasi tenant konteks (scope `MilikTenant` hanya menyaring
        // tabel utama `ProdukSatuan`).
        $idTenant = $this->konteks->Wajib();
        $hasil = ProdukSatuan::query()
            ->join('Produk', fn ($gabung) => $gabung->on('Produk.Id', '=', 'ProdukSatuan.IdProduk')->where('Produk.IdTenant', '=', $idTenant))
            ->whereNull('Produk.DihapusPada')
            ->whereIn('Produk.Jenis', $jenisDijual)
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam->where('Produk.Nama', 'like', $pola)->orWhere('Produk.Sku', 'like', $pola)))
            ->orderByRaw('EXISTS (SELECT 1 FROM ProdukHarga WHERE ProdukHarga.IdProdukSatuan = ProdukSatuan.Id AND ProdukHarga.IdDaftarHarga = ? AND ProdukHarga.IdTenant = ?) DESC', [$daftar->Id, $idTenant])
            ->orderBy('Produk.Nama')
            ->orderBy('ProdukSatuan.Id')
            ->select('ProdukSatuan.*')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $halaman))
            ->withQueryString();
        /** @var list<ProdukSatuan> $satuan */
        $satuan = $hasil->items();
        $idSatuan = array_map(fn (ProdukSatuan $s): int => $s->Id, $satuan);
        $produk = Produk::query()->whereIn('Id', array_map(fn (ProdukSatuan $s): int => $s->IdProduk, $satuan))->get()->keyBy('Id');
        $namaSatuan = Satuan::query()->whereIn('Id', array_map(fn (ProdukSatuan $s): int => $s->IdSatuan, $satuan))->pluck('Nama', 'Id');
        $harga = ProdukHarga::query()
            ->whereIn('IdProdukSatuan', $idSatuan)
            ->where(fn ($kueri) => $kueri->whereNull('IdDaftarHarga')->orWhere('IdDaftarHarga', $daftar->Id))
            ->orderBy('JumlahMinimum')
            ->get();

        return [
            'Data' => array_map(function (ProdukSatuan $s) use ($produk, $namaSatuan, $harga, $daftar): array {
                $p = $produk->get($s->IdProduk);
                $dasar = $harga->first(fn (ProdukHarga $h): bool => $h->IdProdukSatuan === $s->Id && $h->IdDaftarHarga === null && $h->JumlahMinimum === '1.0000');

                return [
                    'UuidProduk' => $p->Uuid ?? '',
                    'NamaProduk' => $p->Nama ?? '',
                    'Sku' => $p?->Sku,
                    'UuidProdukSatuan' => $s->Uuid,
                    'NamaSatuan' => (string) ($namaSatuan->get($s->IdSatuan) ?? ''),
                    'HargaDasar' => $dasar?->Harga,
                    'Harga' => array_values($harga
                        ->filter(fn (ProdukHarga $h): bool => $h->IdProdukSatuan === $s->Id && $h->IdDaftarHarga === $daftar->Id)
                        ->map(fn (ProdukHarga $h): array => ['JumlahMinimum' => $h->JumlahMinimum, 'Harga' => $h->Harga])
                        ->all()),
                ];
            }, $satuan),
            'HalamanSaatIni' => $hasil->currentPage(),
            'HalamanTerakhir' => $hasil->lastPage(),
            'Total' => $hasil->total(),
        ];
    }
}
