<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\DaftarPerangkat;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPajak;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Kueri\MutasiDokumen;

/**
 * Detail penjualan back-office (F-07b, izin `laporan.penjualan.lihat`): ringkasan dokumen, baris (snapshot harga,
 * diskon, pajak, HPP), rincian pajak, pembayaran, mutasi stok (tautan kartu stok), shift, dan jurnal. Penjualan tenant
 * lain atau di outlet di luar akses = null (404).
 */
final class DetailPenjualan
{
    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly DaftarPerangkat $perangkat,
        private readonly AnggotaOutlet $anggota,
        private readonly InfoShift $shift,
        private readonly JurnalSumber $jurnal,
        private readonly MutasiDokumen $mutasi,
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoGudang $infoGudang,
        private readonly KomposisiPenjualan $komposisi,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>|null
     */
    public function Ambil(string $uuid, ?array $idOutletBoleh): ?array
    {
        $p = Penjualan::query()->where('Uuid', $uuid)->first();

        if ($p === null || ($idOutletBoleh !== null && ! in_array($p->IdOutlet, $idOutletBoleh, true))) {
            return null;
        }

        $detail = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get();
        $nama = $this->anggota->AmbilNama(array_values(array_filter([$p->IdPengguna, $p->IdPenyetujuDiskon])));
        $jurnal = $this->jurnal->Ambil(JenisSumberJurnal::Penjualan, $p->Id);
        $simbol = $this->komposisi->AmbilSimbolSatuan(array_values($detail->pluck('IdSatuan')->all()));

        return [
            'Penjualan' => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'Kanal' => $p->Kanal->value,
                'LabelKanal' => $p->Kanal->AmbilLabel(),
                'NamaOutlet' => $this->outlet->AmbilRingkas([$p->IdOutlet])[0]['Nama'] ?? '',
                'Perangkat' => $this->perangkat->AmbilLabel([$p->IdPerangkat])[$p->IdPerangkat] ?? '',
                'NamaKasir' => $nama[$p->IdPengguna]['Nama'] ?? '',
                'NamaPenyetujuDiskon' => $p->IdPenyetujuDiskon === null ? null : ($nama[$p->IdPenyetujuDiskon]['Nama'] ?? ''),
                'DibuatOfflinePada' => $p->DibuatOfflinePada->toIso8601String(),
                'DiterimaPada' => $p->DiterimaPada->toIso8601String(),
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'HargaTermasukPajak' => $p->HargaTermasukPajak,
                'PersenBiayaLayanan' => $p->PersenBiayaLayanan,
                'Subtotal' => $p->Subtotal,
                'DiskonBaris' => $p->DiskonBaris,
                'DiskonPesanan' => $p->DiskonPesanan,
                'TotalDiskon' => $p->TotalDiskon,
                'BiayaLayanan' => $p->BiayaLayanan,
                'TotalPajak' => $p->TotalPajak,
                'Pembulatan' => $p->Pembulatan,
                'TotalAkhir' => $p->TotalAkhir,
                'TotalDibayar' => $p->TotalDibayar,
                'Kembalian' => $p->Kembalian,
                'TotalHpp' => $p->TotalHpp,
                'Catatan' => $p->Catatan,
                'PerluTinjauan' => $p->PerluTinjauan,
                'AlasanTinjauan' => $p->AlasanTinjauan,
                'UuidShift' => $this->shift->AmbilBanyak([$p->IdShift])[$p->IdShift]->uuid ?? null,
            ],
            'Baris' => array_values($detail->map(fn (PenjualanDetail $d): array => [
                'Uuid' => $d->Uuid,
                'NamaProduk' => $d->NamaProduk,
                'Jumlah' => $d->Jumlah,
                'SimbolSatuan' => $simbol[$d->IdSatuan] ?? '',
                'HargaSatuan' => $d->HargaSatuan,
                'HargaPilihan' => $d->HargaPilihan,
                'Pilihan' => array_values(array_map(fn (array $p): string => $p['Nama'], $d->Pilihan ?? [])),
                'Bruto' => $d->Bruto,
                'JumlahDiskon' => $d->JumlahDiskon,
                'JumlahDiskonPesanan' => $d->JumlahDiskonPesanan,
                'JumlahPajak' => $d->JumlahPajak,
                'TotalBaris' => $d->TotalBaris,
                'HppSatuan' => $d->HppSatuan,
                'TotalHpp' => $d->TotalHpp,
                'Catatan' => $d->Catatan,
            ])->all()),
            'Pajak' => array_values(PenjualanPajak::query()->where('IdPenjualan', $p->Id)->orderBy('Id')->get()->map(fn (PenjualanPajak $pajak): array => [
                'KodeJenisPajak' => $pajak->KodeJenisPajak,
                'Tarif' => $pajak->Tarif,
                'DasarPengenaan' => $pajak->DasarPengenaan->AmbilLabel(),
                'Dpp' => $pajak->Dpp,
                'Jumlah' => $pajak->Jumlah,
            ])->all()),
            'Pembayaran' => array_values(PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get()->map(fn (PenjualanPembayaran $b): array => [
                'Uuid' => $b->Uuid,
                'NamaMetode' => $b->NamaMetode,
                'LabelJenis' => $b->JenisMetode->AmbilLabel(),
                'Jumlah' => $b->Jumlah,
                'Referensi' => $b->Referensi,
            ])->all()),
            'MutasiStok' => $this->AmbilMutasi($p->Id),
            'Jurnal' => array_map(fn (array $j): array => ['Uuid' => $j['Uuid'], 'Nomor' => $j['Nomor']], $jurnal),
        ];
    }

    /**
     * @return list<array{Kunci: string, NamaProduk: string, NamaGudang: string, Jumlah: string, SimbolSatuan: string, TotalHpp: string, TautanKartuStok: string|null}>
     */
    private function AmbilMutasi(int $idPenjualan): array
    {
        $mutasi = $this->mutasi->AmbilRingkasan(JenisReferensiMutasi::Penjualan, $idPenjualan);
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_column($mutasi, 'IdProduk'))), true);
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_column($mutasi, 'IdGudang'))));

        return array_map(function (array $m) use ($produk, $gudang): array {
            $p = $produk[$m['IdProduk']] ?? null;
            $g = $gudang[$m['IdGudang']] ?? null;

            return [
                'Kunci' => (string) $m['Id'],
                'NamaProduk' => $p === null ? '' : $p->nama,
                'NamaGudang' => $g === null ? '' : $g->nama,
                'Jumlah' => $m['Jumlah'],
                'SimbolSatuan' => $p === null ? '' : $p->simbolSatuan,
                'TotalHpp' => $m['TotalHpp'],
                'TautanKartuStok' => $p === null || $g === null ? null : '/kelola/persediaan/kartu-stok?'.http_build_query([
                    'produk' => $p->uuid,
                    'gudang' => $g->uuid,
                    'dari' => $m['TanggalBisnis'],
                    'sampai' => $m['TanggalBisnis'],
                ]),
            ];
        }, $mutasi);
    }
}
