<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Peristiwa\PeristiwaIntegrasi;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Data\DataInfoGudang;

/**
 * X7 §16.4 `stok.menipis` (v4.07): dipanggil buku stok (`CatatMutasiStok`) setelah saldo disimpan. Peristiwa dikirim
 * **sekali per penurunan yang melewati batas**: saldo sebelum dokumen > `StokMinimum` dan sesudahnya ≤ `StokMinimum`
 * di lokasi stok itu. Saldo yang sudah di bawah minimum lalu turun lagi tidak mengirim ulang; setelah stok diisi di atas
 * minimum, penurunan berikutnya mengirim lagi. Produk tanpa batas minimum diabaikan. Peristiwa `ShouldDispatchAfterCommit`,
 * jadi dokumen yang dibatalkan transaksinya tidak memberi tahu apa pun.
 */
final class PemberitahuStokMenipis
{
    public function __construct(private readonly InfoProdukStok $infoProduk) {}

    /**
     * @param  array<string, array{IdProduk: int, IdGudang: int, Awal: Kuantitas, Akhir: Kuantitas}>  $perubahan  kunci = "{IdProduk}:{IdGudang}"
     * @param  array<int, DataInfoProdukStok>  $produk
     * @param  array<int, DataInfoGudang>  $gudang
     */
    public function Periksa(int $idTenant, array $perubahan, array $produk, array $gudang, string $kunciDokumen): void
    {
        $turun = array_filter($perubahan, fn (array $p): bool => $p['Akhir']->Bandingkan($p['Awal']) < 0);

        if ($turun === []) {
            return;
        }

        $minimum = $this->infoProduk->AmbilStokMinimum(array_values(array_map(fn (array $p): array => [$p['IdProduk'], $p['IdGudang']], $turun)));

        foreach ($turun as $kunci => $p) {
            if (! isset($minimum[$kunci])) {
                continue;
            }

            $batas = Kuantitas::Dari($minimum[$kunci]);

            if ($p['Awal']->Bandingkan($batas) <= 0 || $p['Akhir']->Bandingkan($batas) > 0) {
                continue;
            }

            $infoProduk = $produk[$p['IdProduk']];
            $infoGudang = $gudang[$p['IdGudang']];

            PeristiwaIntegrasi::dispatch($idTenant, 'stok.menipis', $p['IdProduk'], [
                'UuidProduk' => $infoProduk->uuid,
                'Sku' => $infoProduk->sku,
                'NamaProduk' => $infoProduk->nama,
                'Satuan' => $infoProduk->simbolSatuan,
                'UuidGudang' => $infoGudang->uuid,
                'NamaGudang' => $infoGudang->nama,
                'Saldo' => $p['Akhir']->KeString(),
                'StokMinimum' => $batas->KeString(),
            ], kunci: $p['IdGudang'].'-'.$kunciDokumen);
        }
    }
}
