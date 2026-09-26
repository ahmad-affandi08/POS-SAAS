<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Harga\Kueri\HargaProdukBerlaku;
use App\Domain\Katalog\Layanan\PenyimpanGambarProduk;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Katalog\Pilihan\Model\ProdukKelompokPilihan;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * F-17 Self-Order QR Meja: menu publik satu outlet dan harga baris yang dihitung server (tamu tidak pernah menentukan
 * harga). Produk tampil bila aktif, `TampilDiPos`, bisa dijual (bukan induk varian/bahan baku), bukan anak varian
 * (varian menyusul), punya harga jual kanal `MakanDiTempat` (harga yang sama dengan tagihan meja di kasir), dan setiap
 * kelompok pilihan wajibnya punya cukup pilihan aktif. Satuan = satuan jual bawaan (atau satuan dasar).
 */
final class MenuPesanSendiri
{
    public function __construct(private readonly HargaProdukBerlaku $harga) {}

    /**
     * @param  string  $dasarGambar  URL gambar dengan penanda `{uuid}`
     * @return array{Kategori: list<array{Uuid: string, Nama: string}>, Produk: list<array{Uuid: string, UuidProdukSatuan: string, Nama: string, UuidKategori: string|null, Harga: string, UrlGambar: string|null, KelompokPilihan: list<array{Uuid: string, Nama: string, MinimalPilih: int, MaksimalPilih: int, Pilihan: list<array{Uuid: string, Nama: string, Harga: string}>}>}>}
     */
    public function Ambil(int $idOutlet, string $dasarGambar): array
    {
        $produk = $this->KueriProduk()->orderBy('Nama')->get();
        $satuan = $this->AmbilSatuanJual($produk);
        $kelompok = $this->AmbilKelompok(array_values($produk->map(fn (Produk $p): int => $p->Id)->all()));
        $waktu = CarbonImmutable::now();
        $hasil = [];

        foreach ($produk as $p) {
            $s = $satuan[$p->Id] ?? null;
            $grup = $kelompok[$p->Id] ?? [];

            if (! $s instanceof ProdukSatuan || ! self::CekKelompokBisaDipenuhi($grup)) {
                continue;
            }

            $harga = $this->harga->Tentukan($p, $s, Kuantitas::Dari(1), $idOutlet, KanalPenjualan::MakanDiTempat, null, $waktu);

            if ($harga === null) {
                continue;
            }

            $hasil[] = [
                'Uuid' => $p->Uuid,
                'UuidProdukSatuan' => $s->Uuid,
                'Nama' => $p->Nama,
                'IdKategori' => $p->IdKategori,
                'Harga' => $harga->harga->KeString(),
                'UrlGambar' => PenyimpanGambarProduk::BuatUrl($p, 'kecil', $dasarGambar),
                'KelompokPilihan' => array_map(fn (array $g): array => [
                    'Uuid' => $g['Kelompok']->Uuid,
                    'Nama' => $g['Kelompok']->Nama,
                    'MinimalPilih' => $g['Kelompok']->MinimalPilih,
                    'MaksimalPilih' => $g['Kelompok']->MaksimalPilih,
                    'Pilihan' => array_map(fn (Pilihan $pl): array => ['Uuid' => $pl->Uuid, 'Nama' => $pl->Nama, 'Harga' => Uang::Dari($pl->Harga)->KeString()], $g['Pilihan']),
                ], $grup),
            ];
        }

        $idKategori = array_values(array_unique(array_filter(array_column($hasil, 'IdKategori'))));
        $kategori = Kategori::query()->whereKey($idKategori)->orderBy('Urutan')->orderBy('Nama')->get(['Id', 'Uuid', 'Nama']);
        $uuidKategori = $kategori->pluck('Uuid', 'Id');

        return [
            'Kategori' => array_values($kategori->map(fn (Kategori $k): array => ['Uuid' => $k->Uuid, 'Nama' => $k->Nama])->all()),
            'Produk' => array_map(function (array $p) use ($uuidKategori): array {
                $idKategori = $p['IdKategori'];
                unset($p['IdKategori']);

                return array_slice($p, 0, 3, true) + ['UuidKategori' => $idKategori === null ? null : (string) $uuidKategori->get($idKategori)] + $p;
            }, $hasil),
        ];
    }

    /**
     * Harga server untuk baris pesanan tamu. Produk di luar menu → `ProdukTidakTersedia`; pilihan bukan milik
     * kelompok produk, ganda, nonaktif, atau jumlah per kelompok di luar Minimal/Maksimal → `PilihanTidakValid`.
     *
     * @param  list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>}>  $baris
     * @return list<array{UuidProduk: string, UuidProdukSatuan: string, NamaProduk: string, HargaSatuan: Uang, HargaPilihan: Uang, Pilihan: list<array{UuidPilihan: string, Nama: string, Harga: string}>}>
     */
    public function HitungBaris(int $idOutlet, array $baris): array
    {
        $uuid = array_values(array_unique(array_column($baris, 'UuidProduk')));
        $produk = $this->KueriProduk()->whereIn('Uuid', $uuid)->get()->keyBy('Uuid');
        $satuan = $this->AmbilSatuanJual($produk);
        $kelompok = $this->AmbilKelompok(array_values($produk->map(fn (Produk $p): int => $p->Id)->all()));
        $waktu = CarbonImmutable::now();
        $hasil = [];

        foreach ($baris as $i => $b) {
            $p = $produk->get($b['UuidProduk']);
            $s = $p instanceof Produk ? ($satuan[$p->Id] ?? null) : null;
            $harga = $p instanceof Produk && $s instanceof ProdukSatuan
                ? $this->harga->Tentukan($p, $s, Kuantitas::Dari($b['Jumlah']), $idOutlet, KanalPenjualan::MakanDiTempat, null, $waktu)
                : null;

            if (! $p instanceof Produk || ! $s instanceof ProdukSatuan || $harga === null) {
                throw new PelanggaranAturanBisnis('ProdukTidakTersedia', 'Ada menu yang sudah tidak tersedia. Hapus dari keranjang lalu coba lagi.', "Baris.{$i}.UuidProduk", 422, ['UuidProduk' => $b['UuidProduk']]);
            }

            $pilihan = $this->PeriksaPilihan($kelompok[$p->Id] ?? [], $b['Pilihan'], $p->Nama, $i);
            $hargaPilihan = Uang::Nol();

            foreach ($pilihan as $pl) {
                $hargaPilihan = $hargaPilihan->Tambah(Uang::Dari($pl->Harga));
            }

            $hasil[] = [
                'UuidProduk' => $p->Uuid,
                'UuidProdukSatuan' => $s->Uuid,
                'NamaProduk' => $p->Nama,
                'HargaSatuan' => $harga->harga,
                'HargaPilihan' => $hargaPilihan,
                'Pilihan' => array_map(fn (Pilihan $pl): array => ['UuidPilihan' => $pl->Uuid, 'Nama' => $pl->Nama, 'Harga' => Uang::Dari($pl->Harga)->KeString()], $pilihan),
            ];
        }

        return $hasil;
    }

    /** Produk menu dengan gambar (untuk rute gambar publik); null bila bukan menu. */
    public function CariProduk(string $uuid): ?Produk
    {
        return $this->KueriProduk()->where('Uuid', $uuid)->first();
    }

    /**
     * @return Builder<Produk>
     */
    private function KueriProduk(): Builder
    {
        return Produk::query()
            ->where('Aktif', true)
            ->where('TampilDiPos', true)
            ->whereNull('IdInduk')
            ->whereNotIn('Jenis', array_map(fn (JenisProduk $j): string => $j->value, array_filter(JenisProduk::cases(), fn (JenisProduk $j): bool => ! $j->CekBisaDijual())));
    }

    /**
     * Satuan jual bawaan per produk; tanpa satuan jual bawaan → satuan dasar.
     *
     * @param  Collection<array-key, Produk>  $produk
     * @return array<int, ProdukSatuan>
     */
    private function AmbilSatuanJual(Collection $produk): array
    {
        $dasar = [];

        foreach ($produk as $p) {
            $dasar[$p->Id] = $p->IdSatuanDasar;
        }

        $hasil = [];

        foreach (ProdukSatuan::query()->whereIn('IdProduk', array_keys($dasar))->orderBy('Id')->get() as $s) {
            $lama = $hasil[$s->IdProduk] ?? null;

            if ($s->DefaultJual && ($lama === null || ! $lama->DefaultJual)) {
                $hasil[$s->IdProduk] = $s;
            } elseif ($lama === null && $s->IdSatuan === ($dasar[$s->IdProduk] ?? null)) {
                $hasil[$s->IdProduk] = $s;
            }
        }

        return $hasil;
    }

    /**
     * Kelompok pilihan terpasang per produk (berurutan) beserta pilihan aktifnya.
     *
     * @param  list<int>  $idProduk
     * @return array<int, list<array{Kelompok: KelompokPilihan, Pilihan: list<Pilihan>}>>
     */
    private function AmbilKelompok(array $idProduk): array
    {
        if ($idProduk === []) {
            return [];
        }

        $tautan = ProdukKelompokPilihan::query()->whereIn('IdProduk', $idProduk)->orderBy('Urutan')->orderBy('Id')->get();
        $idKelompok = $tautan->pluck('IdKelompokPilihan')->unique()->values()->all();
        $kelompok = KelompokPilihan::query()->whereKey($idKelompok)->get()->keyBy('Id');
        $pilihan = Pilihan::query()->whereIn('IdKelompokPilihan', $idKelompok)->where('Aktif', true)->orderBy('Urutan')->orderBy('Id')->get()->groupBy('IdKelompokPilihan');
        $hasil = [];

        foreach ($tautan as $t) {
            $k = $kelompok->get($t->IdKelompokPilihan);

            if ($k instanceof KelompokPilihan) {
                $hasil[$t->IdProduk][] = ['Kelompok' => $k, 'Pilihan' => array_values($pilihan->get($k->Id, new Collection)->all())];
            }
        }

        return $hasil;
    }

    /**
     * @param  list<array{Kelompok: KelompokPilihan, Pilihan: list<Pilihan>}>  $grup
     */
    private static function CekKelompokBisaDipenuhi(array $grup): bool
    {
        foreach ($grup as $g) {
            if (count($g['Pilihan']) < $g['Kelompok']->MinimalPilih) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{Kelompok: KelompokPilihan, Pilihan: list<Pilihan>}>  $grup
     * @param  list<string>  $uuidDipilih
     * @return list<Pilihan> pilihan terpilih, urut kelompok lalu urutan pilihan
     */
    private function PeriksaPilihan(array $grup, array $uuidDipilih, string $namaProduk, int $i): array
    {
        $bidang = "Baris.{$i}.Pilihan";

        if (count($uuidDipilih) !== count(array_unique($uuidDipilih))) {
            throw new PelanggaranAturanBisnis('PilihanTidakValid', "Pilihan untuk {$namaProduk} ganda.", $bidang);
        }

        $sisa = array_flip($uuidDipilih);
        $hasil = [];

        foreach ($grup as $g) {
            $terpilih = array_values(array_filter($g['Pilihan'], fn (Pilihan $pl): bool => isset($sisa[$pl->Uuid])));
            $jumlah = count($terpilih);
            $k = $g['Kelompok'];

            if ($jumlah < $k->MinimalPilih || $jumlah > $k->MaksimalPilih) {
                $syarat = $k->MinimalPilih === $k->MaksimalPilih
                    ? "tepat {$k->MinimalPilih}"
                    : ($k->MinimalPilih > 0 ? "{$k->MinimalPilih} sampai {$k->MaksimalPilih}" : "paling banyak {$k->MaksimalPilih}");

                throw new PelanggaranAturanBisnis('PilihanTidakValid', "Pilih {$syarat} {$k->Nama} untuk {$namaProduk}.", $bidang);
            }

            foreach ($terpilih as $pl) {
                unset($sisa[$pl->Uuid]);
                $hasil[] = $pl;
            }
        }

        if ($sisa !== []) {
            throw new PelanggaranAturanBisnis('PilihanTidakValid', "Ada pilihan yang tidak berlaku untuk {$namaProduk}. Muat ulang menu lalu pilih lagi.", $bidang);
        }

        return $hasil;
    }
}
