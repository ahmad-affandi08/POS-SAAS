<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use Carbon\CarbonImmutable;

/**
 * K-19: batch bersisa satu produk di lokasi stok Toko outlet perangkat, urut FEFO (urutan yang dipakai server saat
 * mengalokasikan penjualan, F-05g), untuk info kedaluwarsa di kasir sebelum menjual. `SisaHari` dihitung dari tanggal
 * bisnis outlet; negatif = sudah lewat. Produk tak dikenal (tenant lain/terhapus) = null.
 */
final class BatchProdukPos
{
    /** Batch yang ditampilkan di kasir (sisanya cukup diringkas jumlahnya). */
    private const BATAS_BATCH = 20;

    /** Batas "segera kedaluwarsa", sama dengan laporan stok & Kotak Tindakan (30 hari). */
    public const HARI_SEGERA = 30;

    public function __construct(
        private readonly InfoProdukStok $produk,
        private readonly OutletPenjualan $outlet,
        private readonly PelacakanTersedia $pelacakan,
    ) {}

    /**
     * @return array{UuidProduk: string, Pelacakan: string, SimbolSatuan: string, HariSegera: int, JumlahBatch: int, Batch: list<array{NomorBatch: string, TanggalKedaluwarsa: string|null, JumlahSisa: string, SisaHari: int|null}>}|null
     */
    public function Ambil(int $idOutlet, string $uuidProduk, CarbonImmutable $hariIni): ?array
    {
        $produk = $this->produk->AmbilDariUuid([$uuidProduk])[$uuidProduk] ?? null;

        if ($produk === null) {
            return null;
        }

        $idGudang = $this->outlet->AmbilIdGudangToko($idOutlet);
        $batch = $produk->pelacakan === PelacakanProduk::Batch && $idGudang !== null
            ? $this->pelacakan->Ambil($produk->id, $idGudang)['Batch']
            : [];

        return [
            'UuidProduk' => $produk->uuid,
            'Pelacakan' => $produk->pelacakan->value,
            'SimbolSatuan' => $produk->simbolSatuan,
            'HariSegera' => self::HARI_SEGERA,
            'JumlahBatch' => count($batch),
            'Batch' => array_map(fn (array $b): array => [
                'NomorBatch' => $b['NomorBatch'],
                'TanggalKedaluwarsa' => $b['TanggalKedaluwarsa'],
                'JumlahSisa' => $b['JumlahSisa'],
                'SisaHari' => $b['TanggalKedaluwarsa'] === null ? null : (int) $hariIni->startOfDay()->diffInDays(CarbonImmutable::parse($b['TanggalKedaluwarsa'], $hariIni->getTimezone())->startOfDay(), false),
            ], array_slice($batch, 0, self::BATAS_BATCH)),
        ];
    }
}
