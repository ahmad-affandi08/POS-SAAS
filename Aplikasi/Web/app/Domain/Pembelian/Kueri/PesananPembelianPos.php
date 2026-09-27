<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelianDetail;

/**
 * Modul Gudang aplikasi (POS-25, F-04 langkah 5 GRN): PO yang siap diterima (Disetujui/Diterima sebagian) di lokasi
 * stok outlet perangkat, beserta baris dan sisa yang belum diterima (satuan PO). Baris dirujuk lewat `Urutan` per PO
 * (Id internal tidak dikirim ke perangkat); `UuidProduk` untuk mencocokkan barcode katalog perangkat.
 */
final class PesananPembelianPos
{
    public const BATAS = 50;

    public function __construct(
        private readonly PetaNamaPembelian $peta,
        private readonly InfoProdukStok $infoProduk,
    ) {}

    /**
     * @param  list<int>  $idGudang
     * @return list<array<string, mixed>>
     */
    public function DaftarTerbuka(array $idGudang, string $kata = ''): array
    {
        if ($idGudang === []) {
            return [];
        }

        $kata = trim($kata);
        $po = PesananPembelian::query()
            ->whereIn('IdGudang', $idGudang)
            ->whereIn('Status', [StatusPesananPembelian::Disetujui->value, StatusPesananPembelian::DiterimaSebagian->value])
            ->when($kata !== '', fn ($kueri) => $kueri->where('Nomor', 'like', '%'.addcslashes($kata, '%_\\').'%'))
            ->orderByRaw('PerkiraanTiba IS NULL')
            ->orderBy('PerkiraanTiba')
            ->orderBy('Tanggal')
            ->orderBy('Id')
            ->limit(self::BATAS)
            ->with('Detail')
            ->get();
        $pemasok = $this->peta->Pemasok($po->pluck('IdPemasok')->all());
        $gudang = $this->peta->Gudang($po->pluck('IdGudang')->all());
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($po->flatMap(fn (PesananPembelian $p) => $p->Detail->pluck('IdProduk'))->all())), true);

        return array_values($po->map(fn (PesananPembelian $p): array => [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'Tanggal' => $p->Tanggal->format('Y-m-d'),
            'PerkiraanTiba' => $p->PerkiraanTiba?->format('Y-m-d'),
            'Status' => $p->Status->value,
            'LabelStatus' => $p->Status->AmbilLabel(),
            'NamaPemasok' => $pemasok[$p->IdPemasok]['Nama'] ?? '',
            'NamaGudang' => $gudang[$p->IdGudang]->nama ?? '',
            'Baris' => array_values($p->Detail->map(function (PesananPembelianDetail $d) use ($produk): array {
                $sisa = Kuantitas::Dari($d->Jumlah)->Kurangi(Kuantitas::Dari($d->JumlahDiterima));
                $info = $produk[$d->IdProduk] ?? null;

                return [
                    'Urutan' => $d->Urutan,
                    'UuidProduk' => $info?->uuid,
                    'NamaProduk' => $d->NamaProduk,
                    'Sku' => $d->Sku,
                    'SimbolSatuan' => $d->SimbolSatuan,
                    'Konversi' => $d->Konversi,
                    'Pelacakan' => $info === null ? 'Tidak' : $info->pelacakan->value,
                    'Jumlah' => $d->Jumlah,
                    'JumlahDiterima' => $d->JumlahDiterima,
                    'Sisa' => ($sisa->BernilaiNegatif() ? Kuantitas::Nol() : $sisa)->KeString(),
                ];
            })->all()),
        ])->all());
    }

    /**
     * Id baris PO per `Urutan` (untuk `TerimaBarang`); null bila PO tidak ada atau di luar [idGudang].
     *
     * @param  list<int>  $idGudang
     * @return array<int, int>|null
     */
    public function PetakanUrutan(string $uuid, array $idGudang): ?array
    {
        $po = PesananPembelian::query()->where('Uuid', strtoupper($uuid))->whereIn('IdGudang', $idGudang)->first();

        return $po === null ? null : PesananPembelianDetail::query()->where('IdPesananPembelian', $po->Id)->pluck('Id', 'Urutan')->map(fn ($id): int => (int) $id)->all();
    }
}
