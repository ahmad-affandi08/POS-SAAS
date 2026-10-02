<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Layanan;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Kueri\PelangganUntukApiPublik;
use App\Domain\Penjualan\Kueri\PenjualanUntukApiPublik;

/**
 * Bentuk penjualan & retur di Open API v1 dan muatan webhook (X7): Id internal (outlet, pelanggan, produk) diganti Uuid
 * lewat kueri publik domain masing-masing. Satu sumber untuk `GET /api/v1/penjualan` dan webhook agar bentuknya sama.
 */
final class PenyusunDataPenjualanApi
{
    public function __construct(
        private readonly PenjualanUntukApiPublik $penjualan,
        private readonly PetaUuidOutlet $outlet,
        private readonly PelangganUntukApiPublik $pelanggan,
        private readonly InfoProdukStok $produk,
    ) {}

    /** @return array<string, mixed>|null */
    public function SatuBerdasarkanId(int $idPenjualan): ?array
    {
        return $this->Petakan($this->penjualan->Daftar(0, 1, id: $idPenjualan)['Data'])[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function Retur(int $idRetur): ?array
    {
        $retur = $this->penjualan->Retur($idRetur);

        if ($retur === null) {
            return null;
        }

        $uuidOutlet = $this->outlet->Ambil([(int) $retur['IdOutlet']])[(int) $retur['IdOutlet']] ?? null;
        unset($retur['IdOutlet']);

        return ['Uuid' => $retur['Uuid'], 'Nomor' => $retur['Nomor'], 'UuidOutlet' => $uuidOutlet, ...$retur];
    }

    /**
     * @param  list<array<string, mixed>>  $data  baris `PenjualanUntukApiPublik::Daftar`
     * @return list<array<string, mixed>>
     */
    public function Petakan(array $data): array
    {
        $uuidOutlet = $this->outlet->Ambil(array_values(array_unique(array_map(fn (array $p): int => (int) $p['IdOutlet'], $data))));
        $uuidPelanggan = $this->pelanggan->PetaUuid(array_values(array_filter(array_map(fn (array $p): ?int => is_int($p['IdPelanggan']) ? $p['IdPelanggan'] : null, $data))));
        $idProduk = [];

        foreach ($data as $p) {
            foreach ((array) $p['Baris'] as $b) {
                $idProduk[] = (int) $b['IdProduk'];
            }
        }

        $infoProduk = $this->produk->AmbilBanyak(array_values(array_unique($idProduk)), denganTerhapus: true);

        return array_map(function (array $p) use ($uuidOutlet, $uuidPelanggan, $infoProduk): array {
            $hasil = ['Uuid' => $p['Uuid'], 'Nomor' => $p['Nomor'], 'UuidOutlet' => $uuidOutlet[(int) $p['IdOutlet']] ?? null,
                'UuidPelanggan' => is_int($p['IdPelanggan']) ? ($uuidPelanggan[$p['IdPelanggan']] ?? null) : null];
            unset($p['Uuid'], $p['Nomor'], $p['IdOutlet'], $p['IdPelanggan']);
            $p['Baris'] = array_map(function (array $b) use ($infoProduk): array {
                $uuid = ($infoProduk[(int) $b['IdProduk']] ?? null)?->uuid;
                unset($b['IdProduk']);

                return ['Uuid' => $b['Uuid'], 'UuidProduk' => $uuid, ...array_diff_key($b, ['Uuid' => true])];
            }, (array) $p['Baris']);

            return [...$hasil, ...$p];
        }, $data);
    }
}
