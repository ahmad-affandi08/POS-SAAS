<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use Carbon\CarbonImmutable;

/**
 * X7 Open API v1 (`GET /api/v1/penjualan`): penjualan tenant aktif dalam rentang tanggal bisnis (inklusif) urut `Id`,
 * halaman berbasis kursor, beserta baris & pembayaran. HPP, data persetujuan internal, dan pengguna tidak dikirim.
 * `IdOutlet`/`IdPelanggan` dikembalikan sebagai Id agar pemanggil memetakannya ke Uuid lewat kueri publik domainnya.
 */
final class PenjualanUntukApiPublik
{
    /**
     * @return array{Data: list<array<string, mixed>>, IdTerakhir: int|null}
     */
    public function Daftar(int $setelahId, int $batas, ?CarbonImmutable $dari = null, ?CarbonImmutable $sampai = null, ?string $uuid = null, ?int $id = null): array
    {
        $penjualan = Penjualan::query()
            ->when($uuid !== null, fn ($k) => $k->where('Uuid', $uuid), fn ($k) => $k->where('Id', '>', $setelahId))
            ->when($id !== null, fn ($k) => $k->whereKey($id))
            ->when($dari !== null, fn ($k) => $k->where('TanggalBisnis', '>=', $dari?->toDateString()))
            ->when($sampai !== null, fn ($k) => $k->where('TanggalBisnis', '<=', $sampai?->toDateString()))
            ->orderBy('Id')
            ->limit($batas)
            ->get();
        $id = $penjualan->modelKeys();
        $detail = PenjualanDetail::query()->whereIn('IdPenjualan', $id)->orderBy('Urutan')->get()->groupBy('IdPenjualan');
        $bayar = PenjualanPembayaran::query()->whereIn('IdPenjualan', $id)->orderBy('Urutan')->get()->groupBy('IdPenjualan');

        return [
            'Data' => array_values($penjualan->map(fn (Penjualan $p): array => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'IdOutlet' => $p->IdOutlet,
                'IdPelanggan' => $p->IdPelanggan,
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'Kanal' => $p->Kanal->value,
                'Status' => $p->Status->value,
                'Subtotal' => (string) $p->Subtotal,
                'TotalDiskon' => (string) $p->TotalDiskon,
                'BiayaLayanan' => (string) $p->BiayaLayanan,
                'BiayaKirim' => (string) $p->BiayaKirim,
                'DiskonKirim' => (string) $p->DiskonKirim,
                'TotalPajak' => (string) $p->TotalPajak,
                'Pembulatan' => (string) $p->Pembulatan,
                'TotalAkhir' => (string) $p->TotalAkhir,
                'TotalDibayar' => (string) $p->TotalDibayar,
                'Kembalian' => (string) $p->Kembalian,
                'DibuatOfflinePada' => $p->DibuatOfflinePada->toIso8601ZuluString(),
                'DiterimaPada' => $p->DiterimaPada->toIso8601ZuluString(),
                'Baris' => array_values(collect($detail->get($p->Id, []))->map(fn (PenjualanDetail $d): array => [
                    'Uuid' => $d->Uuid,
                    'IdProduk' => $d->IdProduk,
                    'NamaProduk' => $d->NamaProduk,
                    'Jumlah' => (string) $d->Jumlah,
                    'HargaSatuan' => (string) $d->HargaSatuan,
                    'HargaPilihan' => (string) $d->HargaPilihan,
                    'Pilihan' => array_map(fn (array $x): array => ['Nama' => $x['Nama'], 'Harga' => $x['Harga']], $d->Pilihan ?? []),
                    'JumlahDiskon' => (string) $d->JumlahDiskon,
                    'JumlahPajak' => (string) $d->JumlahPajak,
                    'TotalBaris' => (string) $d->TotalBaris,
                    'Catatan' => $d->Catatan,
                ])->all()),
                'Pembayaran' => array_values(collect($bayar->get($p->Id, []))->map(fn (PenjualanPembayaran $b): array => [
                    'JenisMetode' => $b->JenisMetode->value,
                    'NamaMetode' => $b->NamaMetode,
                    'Jumlah' => (string) $b->Jumlah,
                    'Status' => $b->Status,
                    'DibayarPada' => $b->DibayarPada->toIso8601ZuluString(),
                ])->all()),
            ])->all()),
            'IdTerakhir' => $penjualan->count() < $batas ? null : $penjualan->last()?->Id,
        ];
    }

    /**
     * Retur penjualan untuk webhook `penjualan.diretur` (X7 bagian 2). HPP tidak dikirim.
     *
     * @return array<string, mixed>|null
     */
    public function Retur(int $idRetur): ?array
    {
        $retur = ReturPenjualan::query()->find($idRetur);

        if ($retur === null) {
            return null;
        }

        return [
            'Uuid' => $retur->Uuid,
            'Nomor' => $retur->Nomor,
            'UuidPenjualan' => Penjualan::query()->whereKey($retur->IdPenjualanAsal)->value('Uuid'),
            'IdOutlet' => $retur->IdOutlet,
            'TanggalBisnis' => $retur->TanggalBisnis->toDateString(),
            'Status' => $retur->Status->value,
            'Alasan' => $retur->Alasan,
            'MetodeRefund' => $retur->MetodeRefund->value,
            'TotalNilai' => (string) $retur->TotalNilai,
            'TotalPajak' => (string) $retur->TotalPajak,
            'TotalRefund' => (string) $retur->TotalRefund,
            'DiterimaPada' => $retur->DiterimaPada->toIso8601ZuluString(),
        ];
    }
}
