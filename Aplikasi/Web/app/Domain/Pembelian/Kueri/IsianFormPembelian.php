<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukPembelian;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelianDetail;

/**
 * Isian awal formulir pembelian (F-04 fase 1; tipe FE di `Tipe/Pembelian.ts`): draf PO untuk diubah (baris lengkap
 * dengan pilihan satuan pembelian), baris PO yang masih bisa diterima untuk form GRN, dan baris GRN untuk form retur.
 */
final class IsianFormPembelian
{
    public function __construct(
        private readonly InfoProdukStok $infoProduk,
        private readonly SatuanProdukPembelian $satuan,
        private readonly PetaNamaPembelian $peta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Pesanan(PesananPembelian $po): array
    {
        $detail = $po->Detail()->get();
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($detail->pluck('IdProduk')->all())), true);
        $satuan = $this->satuan->AmbilUntukProduk(array_keys($produk));
        $gudang = $this->peta->Gudang([$po->IdGudang])[$po->IdGudang] ?? null;

        return [
            'Uuid' => $po->Uuid,
            'Nomor' => $po->Nomor,
            'UuidPemasok' => $po->Pemasok->Uuid,
            'UuidGudang' => $gudang->uuid ?? '',
            'Tanggal' => $po->Tanggal->format('Y-m-d'),
            'PerkiraanTiba' => $po->PerkiraanTiba?->format('Y-m-d'),
            'TerminHari' => $po->TerminHari,
            'Ongkir' => $po->Ongkir,
            'Catatan' => $po->Catatan,
            'Baris' => array_values($detail->map(function (PesananPembelianDetail $d) use ($produk, $satuan): array {
                $info = $produk[$d->IdProduk] ?? null;
                $pilihan = $satuan[$d->IdProduk] ?? [];
                $terpilih = array_values(array_filter($pilihan, fn (array $s): bool => $s['Id'] === $d->IdProdukSatuan))[0] ?? null;

                return [
                    'UuidProduk' => $info->uuid ?? '',
                    'NamaProduk' => $d->NamaProduk,
                    'Sku' => $d->Sku,
                    'Pelacakan' => $info?->pelacakan->value ?? 'Tidak',
                    'SimbolSatuan' => $info->simbolSatuan ?? $d->SimbolSatuan,
                    'BolehDesimal' => $info->bolehDesimal ?? false,
                    'Satuan' => array_map(fn (array $s): array => ['Uuid' => $s['Uuid'], 'Simbol' => $s['Simbol'], 'Nama' => $s['Nama'], 'Konversi' => $s['Konversi'], 'DefaultBeli' => $s['DefaultBeli']], $pilihan),
                    'UuidProdukSatuan' => $terpilih['Uuid'] ?? null,
                    'Jumlah' => self::Ringkas($d->Jumlah),
                    'Harga' => $d->Harga,
                    'Diskon' => $d->Diskon,
                ];
            })->all()),
        ];
    }

    /**
     * Baris PO yang masih bersisa (atau bisa diterima dalam toleransi) untuk formulir penerimaan dari PO.
     *
     * @return array<string, mixed>
     */
    public function PenerimaanDariPesanan(PesananPembelian $po): array
    {
        $detail = $po->Detail()->get();
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($detail->pluck('IdProduk')->all())), true);
        $gudang = $this->peta->Gudang([$po->IdGudang])[$po->IdGudang] ?? null;
        $pemasok = $po->Pemasok;

        return [
            'Uuid' => $po->Uuid,
            'Nomor' => $po->Nomor,
            'NamaPemasok' => $pemasok->Nama,
            'UuidGudang' => $gudang->uuid ?? '',
            'NamaGudang' => $gudang->nama ?? '',
            'Ongkir' => $po->Ongkir,
            'OngkirTerpakai' => (string) PenerimaanBarang::query()->where('IdPesananPembelian', $po->Id)->where('Status', 'Diposting')->sum('Ongkir'),
            'Baris' => array_values($detail->map(function (PesananPembelianDetail $d) use ($produk): array {
                $info = $produk[$d->IdProduk] ?? null;
                $sisa = Kuantitas::Dari($d->Jumlah)->Kurangi(Kuantitas::Dari($d->JumlahDiterima));

                return [
                    'IdBarisPesanan' => $d->Id,
                    'NamaProduk' => $d->NamaProduk,
                    'Sku' => $d->Sku,
                    'Pelacakan' => $info === null ? 'Tidak' : $info->pelacakan->value,
                    'SimbolSatuan' => $d->SimbolSatuan,
                    'Konversi' => self::Ringkas($d->Konversi),
                    'BolehDesimal' => $info !== null && $info->bolehDesimal,
                    'Harga' => $d->Harga,
                    'Jumlah' => self::Ringkas($d->Jumlah),
                    'JumlahDiterima' => self::Ringkas($d->JumlahDiterima),
                    'Sisa' => self::Ringkas($sisa->BernilaiNegatif() ? '0' : $sisa->KeString()),
                ];
            })->all()),
        ];
    }

    /** Angka desimal tanpa nol di belakang koma (`12.5000` → `12.5`). */
    private static function Ringkas(string $angka): string
    {
        return (string) Kuantitas::Dari($angka)->KeDesimal()->strippedOfTrailingZeros();
    }
}
