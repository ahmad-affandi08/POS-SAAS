<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\DaftarPerangkat;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Layanan\PetaMutasiPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use App\Domain\Penjualan\Model\ReturPenjualanPembayaran;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Kueri\MutasiDokumen;

/**
 * Detail retur penjualan back-office (F-09 fase 1, izin `laporan.penjualan.lihat`): ringkasan dokumen (penjualan asal,
 * kasir, penyetuju, alasan, refund), baris (jumlah, kondisi, nilai, pajak, HPP, lokasi stok), refund, mutasi stok
 * (tautan kartu stok), shift, dan jurnal J-09.2. Retur tenant lain atau outlet di luar akses = null (404).
 */
final class DetailReturPenjualan
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
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>|null
     */
    public function Ambil(string $uuid, ?array $idOutletBoleh): ?array
    {
        $r = ReturPenjualan::query()->where('Uuid', $uuid)->first();

        if ($r === null || ($idOutletBoleh !== null && ! in_array($r->IdOutlet, $idOutletBoleh, true))) {
            return null;
        }

        $penjualan = Penjualan::query()->whereKey($r->IdPenjualanAsal)->firstOrFail();
        $detail = ReturPenjualanDetail::query()->where('IdReturPenjualan', $r->Id)->orderBy('Urutan')->get();
        $nama = $this->anggota->AmbilNama([$r->IdPengguna, $r->IdPenyetuju]);
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_filter($detail->pluck('IdGudang')->all(), fn (mixed $id): bool => is_int($id)))));

        return [
            'Retur' => [
                'Uuid' => $r->Uuid,
                'Nomor' => $r->Nomor,
                'LabelStatus' => $r->Status->AmbilLabel(),
                'LabelMetodeRefund' => $r->MetodeRefund->AmbilLabel(),
                'UuidPenjualan' => $penjualan->Uuid,
                'NomorPenjualan' => $penjualan->Nomor,
                'WaktuPenjualan' => $penjualan->DibuatOfflinePada->toIso8601String(),
                'NamaOutlet' => $this->outlet->AmbilRingkas([$r->IdOutlet])[0]['Nama'] ?? '',
                'Perangkat' => $this->perangkat->AmbilLabel([$r->IdPerangkat])[$r->IdPerangkat] ?? '',
                'NamaKasir' => $nama[$r->IdPengguna]['Nama'] ?? '',
                'NamaPenyetuju' => $nama[$r->IdPenyetuju]['Nama'] ?? '',
                'Alasan' => $r->Alasan,
                'DibuatOfflinePada' => $r->DibuatOfflinePada->toIso8601String(),
                'DiterimaPada' => $r->DiterimaPada->toIso8601String(),
                'TanggalBisnis' => $r->TanggalBisnis->toDateString(),
                'TotalNilai' => $r->TotalNilai,
                'TotalPajak' => $r->TotalPajak,
                'TotalBiayaLayanan' => $r->TotalBiayaLayanan,
                'TotalRefund' => $r->TotalRefund,
                'RefundTunai' => $r->RefundTunai,
                'TotalHpp' => $r->TotalHpp,
                'PerluTinjauan' => $r->PerluTinjauan,
                'AlasanTinjauan' => $r->AlasanTinjauan,
                'UuidShift' => $this->shift->AmbilBanyak([$r->IdShift])[$r->IdShift]->uuid ?? null,
            ],
            'Baris' => array_values($detail->map(fn (ReturPenjualanDetail $d): array => [
                'Uuid' => $d->Uuid,
                'NamaProduk' => $d->NamaProduk,
                'Jumlah' => $d->Jumlah,
                'Kondisi' => $d->Kondisi->value,
                'LabelKondisi' => $d->Kondisi->AmbilLabel(),
                'NamaGudang' => $d->IdGudang === null ? null : ($gudang[$d->IdGudang]->nama ?? ''),
                'NilaiBaris' => $d->NilaiBaris,
                'Pajak' => $d->Pajak,
                'BiayaLayanan' => $d->BiayaLayanan,
                'TotalHpp' => $d->TotalHpp,
            ])->all()),
            'Refund' => array_values(ReturPenjualanPembayaran::query()->where('IdReturPenjualan', $r->Id)->orderBy('Urutan')->get()->map(fn (ReturPenjualanPembayaran $b): array => [
                'Uuid' => $b->Uuid,
                'NamaMetode' => $b->NamaMetode,
                'LabelJenis' => $b->JenisMetode->AmbilLabel(),
                'Jumlah' => $b->Jumlah,
            ])->all()),
            'MutasiStok' => $this->AmbilMutasi($r->Id),
            'Jurnal' => array_map(fn (array $j): array => ['Uuid' => $j['Uuid'], 'Nomor' => $j['Nomor']], $this->jurnal->Ambil(JenisSumberJurnal::ReturPenjualan, $r->Id)),
        ];
    }

    /**
     * @return list<array{Kunci: string, NamaProduk: string, NamaGudang: string, Jumlah: string, SimbolSatuan: string, TotalHpp: string, TautanKartuStok: string|null}>
     */
    private function AmbilMutasi(int $idRetur): array
    {
        return PetaMutasiPenjualan::Petakan($this->mutasi->AmbilRingkasan(JenisReferensiMutasi::ReturPenjualan, $idRetur), $this->infoProduk, $this->infoGudang);
    }
}
