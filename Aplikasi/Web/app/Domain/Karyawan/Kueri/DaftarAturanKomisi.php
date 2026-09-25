<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Karyawan\Model\AturanKomisi;
use App\Domain\Katalog\Kueri\NamaProduk;
use App\Domain\Katalog\Kueri\PohonKategori;

/** Aturan komisi back-office (F-18, `TabelData` mode lokal, 200 terbaru) dengan nama produk/kategori sasaran. */
final class DaftarAturanKomisi
{
    public const BATAS = 200;

    public function __construct(
        private readonly NamaProduk $namaProduk,
        private readonly PohonKategori $kategori,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Ambil(): array
    {
        $aturan = AturanKomisi::query()->orderByDesc('Id')->limit(self::BATAS)->get();
        $produk = $this->namaProduk->Ambil(array_values(array_filter($aturan->pluck('UuidProduk')->all(), 'is_string')));
        $kategori = array_column($this->kategori->AmbilOpsi(), 'Jalur', 'Uuid');

        return array_values($aturan->map(fn (AturanKomisi $a): array => [
            'Uuid' => $a->Uuid,
            'Nama' => $a->Nama,
            'Cakupan' => $a->Cakupan->value,
            'LabelCakupan' => $a->Cakupan->AmbilLabel(),
            'UuidProduk' => $a->UuidProduk,
            'UuidKategori' => $a->UuidKategori,
            'NamaSasaran' => match (true) {
                $a->UuidProduk !== null => $produk[$a->UuidProduk] ?? 'Produk terhapus',
                $a->UuidKategori !== null => $kategori[$a->UuidKategori] ?? 'Kategori terhapus',
                default => 'Semua produk',
            },
            'LevelStaf' => $a->LevelStaf,
            'Jenis' => $a->Jenis->value,
            'Nilai' => (string) $a->Nilai,
            'Status' => $a->Status->value,
        ])->all());
    }
}
