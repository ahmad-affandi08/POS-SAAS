<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukPembelian;
use App\Domain\Pembelian\Model\PenerimaanBarangDetail;
use App\Domain\Persediaan\Kueri\CariProdukStok;

/**
 * Pencarian produk untuk formulir pembelian (F-04 fase 1; tipe FE `HasilCariProdukPembelian`): hasil
 * `CariProdukStok` (produk berstok, saldo & HPP di lokasi) ditambah daftar satuan pembelian beserta konversinya
 * (satuan beli bawaan lebih dulu). Satuan dasar selalu ada (konversi 1).
 *
 * Audit kemudahan pakai #21: `HargaBeliTerakhir` = harga per satuan beli dari penerimaan barang Diposting terakhir
 * (utamakan pemasok [idPemasok] bila diberikan, selain itu pemasok mana pun) beserta konversi satuannya, supaya
 * formulir PO/penerimaan/belanja stok bisa mengisi harga otomatis. Null bila produk belum pernah diterima.
 */
final class CariProdukPembelian
{
    public function __construct(
        private readonly CariProdukStok $cari,
        private readonly InfoProdukStok $infoProduk,
        private readonly SatuanProdukPembelian $satuan,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Cari(string $kata, ?int $idGudang, int $batas = 20, ?int $idPemasok = null): array
    {
        $hasil = $this->cari->Cari($kata, $idGudang, $batas);
        $info = $this->infoProduk->AmbilDariUuid(array_map(fn (array $p): string => $p['Uuid'], $hasil));
        $idProduk = array_values(array_map(fn ($i): int => $i->id, $info));
        $satuan = $this->satuan->AmbilUntukProduk($idProduk);
        $hargaTerakhir = $this->AmbilHargaTerakhir($idProduk, $idPemasok);

        return array_map(function (array $p) use ($info, $satuan, $hargaTerakhir): array {
            $id = $info[$p['Uuid']]->id ?? 0;

            return [...$p, 'Satuan' => array_map(fn (array $s): array => [
                'Uuid' => $s['Uuid'],
                'Simbol' => $s['Simbol'],
                'Nama' => $s['Nama'],
                'Konversi' => $s['Konversi'],
                'DefaultBeli' => $s['DefaultBeli'],
            ], $satuan[$id] ?? []), 'HargaBeliTerakhir' => $hargaTerakhir[$id] ?? null];
        }, $hasil);
    }

    /**
     * @param  list<int>  $idProduk
     * @return array<int, array{Harga: string, Konversi: string, Tanggal: string, DariPemasokIni: bool}>
     */
    private function AmbilHargaTerakhir(array $idProduk, ?int $idPemasok): array
    {
        if ($idProduk === []) {
            return [];
        }

        $baris = PenerimaanBarangDetail::query()
            ->join('PenerimaanBarang', 'PenerimaanBarang.Id', '=', 'PenerimaanBarangDetail.IdPenerimaanBarang')
            ->whereIn('PenerimaanBarangDetail.IdProduk', $idProduk)
            ->where('PenerimaanBarang.Status', StatusDokumenTerposting::Diposting->value)
            ->orderByDesc('PenerimaanBarang.Tanggal')
            ->orderByDesc('PenerimaanBarang.Id')
            ->orderByDesc('PenerimaanBarangDetail.Id')
            ->limit(count($idProduk) * 20)
            ->get([
                'PenerimaanBarangDetail.IdProduk',
                'PenerimaanBarangDetail.Harga',
                'PenerimaanBarangDetail.Konversi',
                'PenerimaanBarang.Tanggal',
                'PenerimaanBarang.IdPemasok',
            ]);

        $hasil = [];
        foreach ($baris as $b) {
            $id = (int) $b->IdProduk;
            $dariPemasokIni = $idPemasok !== null && (int) $b->getAttribute('IdPemasok') === $idPemasok;
            // Baris terbaru lebih dulu: simpan yang pertama, lalu ganti sekali bila nanti ada baris dari pemasok ini.
            if (isset($hasil[$id]) && ($hasil[$id]['DariPemasokIni'] || ! $dariPemasokIni)) {
                continue;
            }
            $tanggal = $b->getAttribute('Tanggal');
            $hasil[$id] = [
                'Harga' => (string) $b->Harga,
                'Konversi' => (string) $b->Konversi,
                'Tanggal' => $tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d') : substr((string) $tanggal, 0, 10),
                'DariPemasokIni' => $dariPemasokIni,
            ];
        }

        return $hasil;
    }
}
