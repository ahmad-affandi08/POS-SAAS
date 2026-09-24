<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Harga\Data\HasilSimpanHarga;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Layanan\PencatatRiwayatHarga;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan harga dasar & harga bertingkat (lapis 4–5 price engine F-03) per satuan produk, dengan riwayat harga
 * (BR-03.3). Dipakai form harga, form produk (harga awal satuan baru), generasi varian, impor, dan panduan awal F-01.
 *
 * - `$perSatuan` = `[IdProdukSatuan => list<DataBarisHarga>]`. Untuk setiap satuan yang disebut, seluruh baris dasar
 *   (`IdDaftarHarga` null) **diganti** dengan daftar itu; satuan yang tidak disebut tidak disentuh. Daftar kosong =
 *   harga dasar satuan dihapus (satuan tidak bisa dijual lagi).
 * - Validasi: daftar tidak kosong wajib punya `JumlahMinimum = 1` (`HargaDasarWajib`); `JumlahMinimum` unik
 *   (`JumlahMinimumGanda`), > 0, dan bulat bila satuannya tidak boleh desimal (`JumlahMinimumTidakValid`);
 *   `0 ≤ Harga ≤ 9999999999999999.99` (`HargaTidakValid`); satuan harus milik produk (`SatuanTidakDikenal`).
 *   Bidang galat: `Satuan.{i}.Harga.{j}.{JumlahMinimum|Harga}`, `i` = urutan satuan di `$perSatuan`.
 * - Beda per (satuan, `JumlahMinimum`): tambah, ubah bila harga berbeda, hapus (dengan jejak `PenghapusanKatalog`).
 *   Setiap beda = satu `RiwayatHarga`. Tanpa beda = tanpa tulis (idempoten).
 * - Audit `produk.harga.ubah` (nilai lama/baru satuan yang berubah), kecuali sumber `Impor` (impor mengaudit per
 *   potongan).
 *
 * Urutan kunci: Tenant → baris `ProdukHarga` dasar produk (FOR UPDATE).
 */
final class SimpanHargaProduk
{
    public const HARGA_MAKSIMUM = '9999999999999999.99';

    public const JUMLAH_MINIMUM_MAKSIMUM = '99999999999999.9999';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
        private readonly PencatatRiwayatHarga $riwayat,
        private readonly PencatatPenghapusanKatalog $penghapusan,
    ) {}

    /**
     * @param  array<int, list<DataBarisHarga>>  $perSatuan
     */
    public function Jalankan(Produk $produk, array $perSatuan, SumberPerubahanHarga $sumber): HasilSimpanHarga
    {
        $idTenant = $this->konteks->Wajib();

        if ($perSatuan === []) {
            return new HasilSimpanHarga(0, 0, 0);
        }

        return DB::transaction(function () use ($idTenant, $produk, $perSatuan, $sumber): HasilSimpanHarga {
            $this->penguncian->Kunci($idTenant);

            $satuan = ProdukSatuan::query()
                ->where('IdProduk', $produk->Id)
                ->whereIn('Id', array_keys($perSatuan))
                ->with('SatuanUnit')
                ->get()
                ->keyBy('Id');
            $satuanValid = self::Validasi($perSatuan, $satuan);

            $lama = [];

            foreach (ProdukHarga::query()->where('IdProduk', $produk->Id)->whereNull('IdDaftarHarga')->orderBy('Id')->lockForUpdate()->get() as $baris) {
                if (array_key_exists($baris->IdProdukSatuan, $perSatuan)) {
                    $lama[$baris->IdProdukSatuan][$baris->JumlahMinimum] = $baris;
                }
            }

            $ditambah = $diubah = $dihapus = 0;
            $auditLama = $auditBaru = [];

            foreach ($perSatuan as $idProdukSatuan => $daftar) {
                $produkSatuan = $satuanValid[$idProdukSatuan];
                $lamaSatuan = $lama[$idProdukSatuan] ?? [];
                $baru = [];

                foreach ($daftar as $baris) {
                    $baru[$baris->jumlahMinimum->KeString()] = $baris->harga;
                }

                ksort($baru, SORT_NATURAL);
                $berubah = false;

                foreach ($baru as $jumlahMinimum => $harga) {
                    $jumlahMinimum = (string) $jumlahMinimum;
                    $barisLama = $lamaSatuan[$jumlahMinimum] ?? null;

                    if ($barisLama === null) {
                        ProdukHarga::query()->create([
                            'IdProduk' => $produk->Id,
                            'IdProdukSatuan' => $idProdukSatuan,
                            'IdDaftarHarga' => null,
                            'JumlahMinimum' => $jumlahMinimum,
                            'Harga' => $harga->KeString(),
                        ]);
                        $this->riwayat->Catat($produk->Id, $idProdukSatuan, $produkSatuan->IdSatuan, null, $jumlahMinimum, null, $harga->KeString(), $sumber);
                        $ditambah++;
                        $berubah = true;
                    } elseif (! Uang::Dari($barisLama->Harga)->SamaDengan($harga)) {
                        $hargaLama = $barisLama->Harga;
                        $barisLama->fill(['Harga' => $harga->KeString()])->save();
                        $this->riwayat->Catat($produk->Id, $idProdukSatuan, $produkSatuan->IdSatuan, null, $jumlahMinimum, $hargaLama, $harga->KeString(), $sumber);
                        $diubah++;
                        $berubah = true;
                    }
                }

                foreach ($lamaSatuan as $jumlahMinimum => $barisLama) {
                    if (array_key_exists($jumlahMinimum, $baru)) {
                        continue;
                    }

                    $this->riwayat->Catat($produk->Id, $idProdukSatuan, $produkSatuan->IdSatuan, null, (string) $jumlahMinimum, $barisLama->Harga, null, $sumber);
                    $this->penghapusan->Catat(EntitasKatalog::ProdukHarga, $barisLama->Uuid);
                    $barisLama->delete();
                    $dihapus++;
                    $berubah = true;
                }

                if ($berubah) {
                    $auditLama[$produkSatuan->Uuid] = self::RingkasBaris(array_map(fn (ProdukHarga $baris): string => $baris->Harga, $lamaSatuan));
                    $auditBaru[$produkSatuan->Uuid] = self::RingkasBaris(array_map(fn (Uang $harga): string => $harga->KeString(), $baru));
                }
            }

            $hasil = new HasilSimpanHarga($ditambah, $diubah, $dihapus);

            if ($hasil->CekAdaPerubahan() && $sumber !== SumberPerubahanHarga::Impor) {
                $this->audit->Catat('produk.harga.ubah', $produk, ['Harga' => $auditLama], ['Harga' => $auditBaru, 'Sumber' => $sumber->value]);
            }

            return $hasil;
        });
    }

    /**
     * @param  array<int, list<DataBarisHarga>>  $perSatuan
     * @param  Collection<int, ProdukSatuan>  $satuan
     * @return array<int, ProdukSatuan> satuan produk per IdProdukSatuan yang disebut (semuanya milik produk)
     */
    private static function Validasi(array $perSatuan, Collection $satuan): array
    {
        $hasil = [];
        $satu = Kuantitas::Dari(1);
        $nol = Kuantitas::Nol();
        $jumlahMaksimum = Kuantitas::Dari(self::JUMLAH_MINIMUM_MAKSIMUM);
        $hargaMaksimum = Uang::Dari(self::HARGA_MAKSIMUM);
        $i = 0;

        foreach ($perSatuan as $idProdukSatuan => $daftar) {
            $produkSatuan = $satuan->get($idProdukSatuan);

            if (! $produkSatuan instanceof ProdukSatuan) {
                throw new PelanggaranAturanBisnis('SatuanTidakDikenal', 'Satuan ini bukan satuan produk tersebut. Muat ulang halaman lalu coba lagi.', "Satuan.{$i}");
            }

            $hasil[$idProdukSatuan] = $produkSatuan;
            $bolehDesimal = $produkSatuan->SatuanUnit->BolehDesimal;
            $terpakai = [];
            $adaDasar = false;

            foreach (array_values($daftar) as $j => $baris) {
                $jumlah = $baris->jumlahMinimum;
                $bidang = "Satuan.{$i}.Harga.{$j}";

                if ($jumlah->Bandingkan($nol) <= 0 || $jumlah->Bandingkan($jumlahMaksimum) > 0
                    || (! $bolehDesimal && ! $jumlah->KeDesimal()->getFractionalPart()->isZero())) {
                    throw new PelanggaranAturanBisnis(
                        'JumlahMinimumTidakValid',
                        $bolehDesimal ? 'Jumlah minimum harus lebih dari 0.' : 'Jumlah minimum harus bilangan bulat lebih dari 0 untuk satuan ini.',
                        "{$bidang}.JumlahMinimum",
                    );
                }

                if (in_array($jumlah->KeString(), $terpakai, true)) {
                    throw new PelanggaranAturanBisnis('JumlahMinimumGanda', 'Jumlah minimum ini sudah dipakai baris lain di satuan yang sama.', "{$bidang}.JumlahMinimum");
                }

                if ($baris->harga->BernilaiNegatif() || $baris->harga->Bandingkan($hargaMaksimum) > 0) {
                    throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga harus antara Rp 0 dan Rp 9.999.999.999.999.999,99.', "{$bidang}.Harga");
                }

                $terpakai[] = $jumlah->KeString();
                $adaDasar = $adaDasar || $jumlah->SamaDengan($satu);
            }

            if ($daftar !== [] && ! $adaDasar) {
                throw new PelanggaranAturanBisnis('HargaDasarWajib', 'Isi harga untuk jumlah minimum 1 (harga dasar) sebelum menambah harga bertingkat.', "Satuan.{$i}.Harga");
            }

            $i++;
        }

        return $hasil;
    }

    /**
     * @param  array<array-key, string>  $hargaPerJumlah
     * @return list<array{JumlahMinimum: string, Harga: string}>
     */
    private static function RingkasBaris(array $hargaPerJumlah): array
    {
        ksort($hargaPerJumlah, SORT_NATURAL);
        $hasil = [];

        foreach ($hargaPerJumlah as $jumlahMinimum => $harga) {
            $hasil[] = ['JumlahMinimum' => (string) $jumlahMinimum, 'Harga' => $harga];
        }

        return $hasil;
    }
}
