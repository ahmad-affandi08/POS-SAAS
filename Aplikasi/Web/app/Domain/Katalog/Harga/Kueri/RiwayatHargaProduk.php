<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Harga\Model\RiwayatHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Kueri\DaftarAnggota;

/**
 * Riwayat harga satu produk (BR-03.3, tipe FE `BarisRiwayatHarga`), terbaru dulu, 25 per halaman.
 */
final class RiwayatHargaProduk
{
    public const PER_HALAMAN = 25;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(Produk $produk, int $halaman = 1): array
    {
        $hasil = RiwayatHarga::query()
            ->where('IdProduk', $produk->Id)
            ->orderByDesc('DibuatPada')
            ->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $halaman))
            ->withQueryString();
        /** @var list<RiwayatHarga> $baris */
        $baris = $hasil->items();
        $namaSatuan = Satuan::query()->whereIn('Id', array_map(fn (RiwayatHarga $r): int => $r->IdSatuan, $baris))->pluck('Nama', 'Id');
        $namaDaftar = DaftarHarga::query()->whereIn('Id', array_filter(array_map(fn (RiwayatHarga $r): ?int => $r->IdDaftarHarga, $baris)))->pluck('Nama', 'Id');
        $idPengubah = array_values(array_unique(array_filter(array_map(fn (RiwayatHarga $r): ?int => $r->DiubahOleh, $baris))));
        $namaPengubah = $idPengubah === [] ? [] : $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $idPengubah);

        return [
            'Data' => array_map(fn (RiwayatHarga $r): array => [
                'DibuatPada' => $r->DibuatPada?->utc()->toIso8601ZuluString() ?? '',
                'NamaSatuan' => (string) ($namaSatuan->get($r->IdSatuan) ?? ''),
                'NamaDaftarHarga' => $r->IdDaftarHarga === null ? null : (string) ($namaDaftar->get($r->IdDaftarHarga) ?? ''),
                'JumlahMinimum' => $r->JumlahMinimum,
                'HargaLama' => $r->HargaLama,
                'HargaBaru' => $r->HargaBaru,
                'NamaPengubah' => $r->DiubahOleh === null ? null : ($namaPengubah[$r->DiubahOleh] ?? null),
                'Sumber' => $r->Sumber->value,
                'LabelSumber' => $r->Sumber->AmbilLabel(),
            ], $baris),
            'HalamanSaatIni' => $hasil->currentPage(),
            'HalamanTerakhir' => $hasil->lastPage(),
            'Total' => $hasil->total(),
        ];
    }
}
