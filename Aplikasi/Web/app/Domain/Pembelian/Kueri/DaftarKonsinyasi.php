<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Pembelian\Enum\JenisDokumenKonsinyasi;
use App\Domain\Pembelian\Model\DokumenKonsinyasi;
use App\Domain\Pembelian\Model\DokumenKonsinyasiDetail;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PembayaranKonsinyasi;
use App\Domain\Pembelian\Model\PenitipProduk;
use App\Domain\Persediaan\Kueri\RingkasanMutasiKonsinyasi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tampilan konsinyasi F-05i (`/kelola/pembelian/konsinyasi`): daftar penitip beserta hutangnya, daftar dokumen
 * titipan (server, saring `Jenis` & `Pemasok`), rincian dokumen, dan rincian per penitip (per produk: masuk, retur,
 * terjual, nilai terjual, sisa stok dalam rentang tanggal; serta riwayat setoran). Uang & jumlah sebagai string.
 */
final class DaftarKonsinyasi
{
    public const KOLOM_URUT = ['Nomor', 'Tanggal', 'TotalNilai'];

    public const KOLOM_SARING = ['Jenis', 'Pemasok'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(
        private readonly HutangKonsinyasi $hutang,
        private readonly RingkasanMutasiKonsinyasi $ringkasan,
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoGudang $infoGudang,
        private readonly JurnalSumber $jurnal,
        private readonly DaftarAkunPilihan $akun,
    ) {}

    /**
     * Semua penitip (pemasok yang punya produk titipan atau setoran), urut sisa hutang terbesar.
     *
     * @return array{Penitip: list<array{Uuid: string, Kode: string, Nama: string, JumlahProduk: int, Terjual: string, Dibayar: string, Sisa: string}>, TotalSisa: string}
     */
    public function Penitip(): array
    {
        $hutang = $this->hutang->Ambil();
        $pemasok = Pemasok::query()->withTrashed()->whereKey(array_keys($hutang))->get()->keyBy('Id');
        $baris = [];
        $total = Uang::Nol();

        foreach ($hutang as $id => $h) {
            $p = $pemasok->get($id);

            if (! $p instanceof Pemasok) {
                continue;
            }

            $total = $total->Tambah($h['Sisa']);
            $baris[] = [
                'Uuid' => $p->Uuid,
                'Kode' => $p->Kode,
                'Nama' => $p->Nama,
                'JumlahProduk' => $h['JumlahProduk'],
                'Terjual' => $h['Terjual']->KeString(),
                'Dibayar' => $h['Dibayar']->KeString(),
                'Sisa' => $h['Sisa']->KeString(),
            ];
        }

        usort($baris, fn (array $a, array $b): int => Uang::Dari($b['Sisa'])->Bandingkan(Uang::Dari($a['Sisa'])) ?: strcmp($a['Nama'], $b['Nama']));

        return ['Penitip' => $baris, 'TotalSisa' => $total->KeString()];
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Dokumen(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $jenis = $p->AmbilDaftar('Jenis', array_map(fn (JenisDokumenKonsinyasi $j): string => $j->value, JenisDokumenKonsinyasi::cases()));
        $uuidPemasok = $p->AmbilDaftar('Pemasok');
        $pola = PenerapKueriTabel::PolaCari($p->cari);

        $kueri = DokumenKonsinyasi::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($jenis !== [], fn ($k) => $k->whereIn('Jenis', $jenis))
            ->when($uuidPemasok !== [], fn ($k) => $k->whereIn('IdPemasok', Pemasok::query()->withTrashed()->whereIn('Uuid', $uuidPemasok)->select('Id')))
            ->when($p->cari !== '', fn ($k) => $k->where('Nomor', 'like', $pola));

        return PenerapKueriTabel::Terapkan($kueri, $p, array_combine(self::KOLOM_URUT, self::KOLOM_URUT), function (Collection $baris): array {
            /** @var Collection<int, DokumenKonsinyasi> $baris */
            $pemasok = Pemasok::query()->withTrashed()->whereKey($baris->pluck('IdPemasok')->unique()->values()->all())->get()->keyBy('Id');
            $gudang = $this->infoGudang->AmbilBanyak(array_values(array_map('intval', $baris->pluck('IdGudang')->unique()->values()->all())));

            return array_values($baris->map(fn (DokumenKonsinyasi $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->toDateString(),
                'Jenis' => $d->Jenis->value,
                'LabelJenis' => $d->Jenis->AmbilLabel(),
                'NamaPemasok' => $pemasok->get($d->IdPemasok)->Nama ?? '',
                'NamaGudang' => $gudang[$d->IdGudang]->nama ?? '',
                'TotalNilai' => (string) $d->TotalNilai,
            ])->all());
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function DetailDokumen(DokumenKonsinyasi $d): array
    {
        $detail = DokumenKonsinyasiDetail::query()->where('IdDokumenKonsinyasi', $d->Id)->orderBy('Id')->get();
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_map('intval', $detail->pluck('IdProduk')->all())), denganTerhapus: true);
        $pemasok = Pemasok::query()->withTrashed()->find($d->IdPemasok);
        $gudang = $this->infoGudang->AmbilBanyak([$d->IdGudang])[$d->IdGudang] ?? null;

        return [
            'Dokumen' => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Jenis' => $d->Jenis->value,
                'LabelJenis' => $d->Jenis->AmbilLabel(),
                'Tanggal' => $d->Tanggal->toDateString(),
                'TotalNilai' => (string) $d->TotalNilai,
                'Catatan' => $d->Catatan,
                'UuidPemasok' => $pemasok?->Uuid,
                'NamaPemasok' => $pemasok->Nama ?? '',
                'NamaGudang' => $gudang->nama ?? '',
                'NamaOutlet' => $gudang?->namaOutlet,
            ],
            'Baris' => array_values($detail->map(fn (DokumenKonsinyasiDetail $b): array => [
                'Id' => $b->Id,
                'NamaProduk' => $produk[$b->IdProduk]->nama ?? '',
                'Sku' => $produk[$b->IdProduk]->sku ?? null,
                'SimbolSatuan' => $produk[$b->IdProduk]->simbolSatuan ?? '',
                'Jumlah' => (string) $b->Jumlah,
                'HargaSatuan' => (string) $b->HargaSatuan,
                'Nilai' => (string) $b->Nilai,
            ])->all()),
        ];
    }

    /**
     * Rincian satu penitip dalam rentang tanggal bisnis (inklusif): per produk titipan, ringkasan hutang (sepanjang
     * waktu), dan setoran terakhir (maks. 100).
     *
     * @return array<string, mixed>
     */
    public function RincianPenitip(Pemasok $pemasok, CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $idProduk = array_values(array_map('intval', PenitipProduk::query()->where('IdPemasok', $pemasok->Id)->pluck('IdProduk')->all()));
        $ringkasan = $this->ringkasan->Ambil($idProduk, $dari, $sampai);
        $saldo = $this->ringkasan->Ambil($idProduk);
        $produk = $this->infoProduk->AmbilBanyak($idProduk, denganTerhapus: true);
        $hutang = $this->hutang->Ambil([$pemasok->Id])[$pemasok->Id];
        $baris = [];
        $nilaiPeriode = Uang::Nol();

        foreach ($idProduk as $id) {
            $r = $ringkasan[$id];
            $nilaiPeriode = $nilaiPeriode->Tambah($r['NilaiTerjual']);
            $baris[] = [
                'Uuid' => $produk[$id]->uuid ?? '',
                'NamaProduk' => $produk[$id]->nama ?? '',
                'Sku' => $produk[$id]->sku ?? null,
                'SimbolSatuan' => $produk[$id]->simbolSatuan ?? '',
                'Masuk' => $r['Masuk']->KeString(),
                'Retur' => $r['Retur']->KeString(),
                'Terjual' => $r['Terjual']->KeString(),
                'NilaiTerjual' => $r['NilaiTerjual']->KeString(),
                'Saldo' => $saldo[$id]['Saldo']->KeString(),
            ];
        }

        usort($baris, fn (array $a, array $b): int => strcmp($a['NamaProduk'], $b['NamaProduk']));
        $setoran = PembayaranKonsinyasi::query()->where('IdPemasok', $pemasok->Id)->orderByDesc('Tanggal')->orderByDesc('Id')->limit(100)->get();
        $akun = [];

        foreach ($this->akun->AmbilKasBank() as $a) {
            $akun[$a['Id']] = $a['Nama'];
        }

        return [
            'Penitip' => ['Uuid' => $pemasok->Uuid, 'Kode' => $pemasok->Kode, 'Nama' => $pemasok->Nama, 'NoHp' => $pemasok->NoHp, 'NamaBank' => $pemasok->NamaBank, 'NomorRekening' => $pemasok->NomorRekening, 'AtasNamaRekening' => $pemasok->AtasNamaRekening],
            'Hutang' => ['Terjual' => $hutang['Terjual']->KeString(), 'Dibayar' => $hutang['Dibayar']->KeString(), 'Sisa' => $hutang['Sisa']->KeString()],
            'Periode' => ['Dari' => $dari->toDateString(), 'Sampai' => $sampai->toDateString(), 'NilaiTerjual' => $nilaiPeriode->KeString()],
            'Produk' => $baris,
            'Setoran' => array_values($setoran->map(fn (PembayaranKonsinyasi $s): array => [
                'Uuid' => $s->Uuid,
                'Nomor' => $s->Nomor,
                'Tanggal' => $s->Tanggal->toDateString(),
                'Jumlah' => (string) $s->Jumlah,
                'Status' => $s->Status->value,
                'NamaAkun' => $akun[$s->IdAkunKasBank] ?? '',
                'Catatan' => $s->Catatan,
                'AlasanBatal' => $s->AlasanBatal,
                'Jurnal' => $this->jurnal->Ambil(JenisSumberJurnal::PembayaranKonsinyasi, $s->Id),
            ])->all()),
        ];
    }
}
