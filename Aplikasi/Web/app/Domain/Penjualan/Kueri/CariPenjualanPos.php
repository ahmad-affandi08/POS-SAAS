<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Layanan\PencatatPiutangPenjualan;
use App\Domain\Penjualan\Data\DataSudahDiretur;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Layanan\PenghitungNilaiRetur;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Carbon\CarbonImmutable;

/**
 * `GET /api/pos/v1/penjualan/cari?nomor=` (F-09 fase 1): struk asal untuk layar retur POS. Hanya penjualan outlet
 * perangkat (lainnya = null → 404). Per baris: snapshot harga & nilai, jumlah yang sudah dan masih bisa diretur, serta
 * sisa nilai (`NilaiBisaDiretur`, dipakai perangkat bila retur menghabiskan sisa baris). `BisaDiretur` = status
 * lunas/diretur sebagian dan belum lewat `BatasHariRetur` (dihitung dari tanggal bisnis outlet saat ini);
 * `AlasanTidakBisaDiretur`: `Void`, `SudahDireturPenuh`, `LewatBatasHari`. Kunci tambahan per baris (kompatibel mundur):
 * `BolehDesimal` (satuan dasar produk boleh jumlah desimal) dan `UuidProdukSatuan` (satuan jual produk yang dipakai
 * baris; null bila tidak ditemukan lagi). F-12: `SisaPiutang` (null = bukan penjualan tempo): retur memotong piutang ini lebih
 * dulu lewat metode Tempo, sisanya tunai/transfer.
 */
final class CariPenjualanPos
{
    public function __construct(
        private readonly PenghitungNilaiRetur $penghitung,
        private readonly AnggotaOutlet $anggota,
        private readonly KomposisiPenjualan $komposisi,
        private readonly InfoProdukStok $infoProduk,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PengaturanKasirTenant $pengaturanKasir,
        private readonly PencatatPiutangPenjualan $piutang,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function Ambil(string $nomor, int $idOutlet): ?array
    {
        $p = Penjualan::query()->where('Nomor', $nomor)->where('IdOutlet', $idOutlet)->first();

        if ($p === null) {
            return null;
        }

        $detail = PenjualanDetail::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get();
        $sudah = $this->penghitung->AmbilSudahDiretur(array_values(array_map('intval', $detail->pluck('Id')->all())));
        $simbol = $this->komposisi->AmbilSimbolSatuan(array_values(array_map('intval', $detail->pluck('IdSatuan')->all())));
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map('intval', $detail->pluck('IdProduk')->all()))), true);
        $satuanProduk = $this->komposisi->AmbilProduk(array_values(array_filter(array_map(fn ($info): string => $info->uuid, $produk))));
        $nama = $this->anggota->AmbilNama([$p->IdPengguna]);
        $batasHari = $this->pengaturanKasir->Ambil()->batasHariRetur;
        $batasSampai = CarbonImmutable::parse($p->TanggalBisnis->toDateString())->addDays($batasHari);
        $alasan = match (true) {
            $p->Status === StatusPenjualan::Void => 'Void',
            $p->Status === StatusPenjualan::Diretur => 'SudahDireturPenuh',
            $this->tanggalBisnis->Hitung($idOutlet)->greaterThan($batasSampai) => 'LewatBatasHari',
            default => null,
        };
        $metode = MetodePembayaran::query()
            ->whereIn('Id', PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->select('IdMetodePembayaran'))
            ->pluck('Uuid', 'Id')
            ->all();

        return [
            'Penjualan' => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'DibuatPada' => $p->DibuatOfflinePada->utc()->toIso8601ZuluString(),
                'NamaKasir' => $nama[$p->IdPengguna]['Nama'] ?? '',
                'HargaTermasukPajak' => $p->HargaTermasukPajak,
                'Subtotal' => $p->Subtotal,
                'TotalDiskon' => $p->TotalDiskon,
                'BiayaLayanan' => $p->BiayaLayanan,
                'TotalPajak' => $p->TotalPajak,
                'Pembulatan' => $p->Pembulatan,
                'TotalAkhir' => $p->TotalAkhir,
                'TotalDibayar' => $p->TotalDibayar,
                'Kembalian' => $p->Kembalian,
                'BatasHariRetur' => $batasHari,
                'BatasReturSampai' => $batasSampai->toDateString(),
                'BisaDiretur' => $alasan === null,
                'AlasanTidakBisaDiretur' => $alasan,
                'SisaPiutang' => $this->piutang->AmbilSisa($p->Id)?->KeString(),
            ],
            'Baris' => array_values($detail->map(function (PenjualanDetail $d) use ($sudah, $simbol, $produk, $satuanProduk): array {
                $s = $sudah[$d->Id] ?? DataSudahDiretur::Kosong();
                $info = $produk[$d->IdProduk] ?? null;
                $uuidSatuan = null;

                foreach ($info === null ? [] : ($satuanProduk[$info->uuid]->satuan ?? []) as $uuid => $satuan) {
                    if ($satuan['IdSatuan'] === $d->IdSatuan) {
                        $uuidSatuan = $uuid;

                        break;
                    }
                }

                return [
                    'Uuid' => $d->Uuid,
                    'UuidProduk' => $produk[$d->IdProduk]->uuid ?? null,
                    'NamaProduk' => $d->NamaProduk,
                    'SimbolSatuan' => $simbol[$d->IdSatuan] ?? '',
                    'UuidProdukSatuan' => $uuidSatuan,
                    'BolehDesimal' => $info->bolehDesimal ?? false,
                    'Jumlah' => $d->Jumlah,
                    'HargaSatuan' => $d->HargaSatuan,
                    'HargaPilihan' => $d->HargaPilihan,
                    'Pilihan' => $d->Pilihan ?? [],
                    'Bruto' => $d->Bruto,
                    'JumlahDiskon' => $d->JumlahDiskon,
                    'JumlahDiskonPesanan' => $d->JumlahDiskonPesanan,
                    'BiayaLayanan' => $d->BiayaLayanan,
                    'JumlahPajak' => $d->JumlahPajak,
                    'TotalBaris' => $d->TotalBaris,
                    'SnapshotPajak' => $d->SnapshotPajak ?? [],
                    'JumlahSudahDiretur' => $s->jumlah->KeString(),
                    'JumlahBisaDiretur' => $this->penghitung->HitungSisa($d, $s)->KeString(),
                    'NilaiBisaDiretur' => $this->penghitung->HitungSisaNilai($d, $s)->KeString(),
                ];
            })->all()),
            'Pembayaran' => array_values(PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get()->map(fn (PenjualanPembayaran $b): array => [
                'Uuid' => $b->Uuid,
                'UuidMetodePembayaran' => $metode[$b->IdMetodePembayaran] ?? null,
                'JenisMetode' => $b->JenisMetode->value,
                'NamaMetode' => $b->NamaMetode,
                'Jumlah' => $b->Jumlah,
                'Referensi' => $b->Referensi,
            ])->all()),
            'Retur' => array_values(ReturPenjualan::query()->where('IdPenjualanAsal', $p->Id)->orderBy('Id')->get()->map(fn (ReturPenjualan $r): array => [
                'Uuid' => $r->Uuid,
                'Nomor' => $r->Nomor,
                'DibuatPada' => $r->DibuatOfflinePada->utc()->toIso8601ZuluString(),
                'TotalRefund' => $r->TotalRefund,
            ])->all()),
        ];
    }
}
