<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataProduk;
use App\Domain\Katalog\Data\DataProdukCepat;
use App\Domain\Katalog\Data\DataSatuanProduk;
use App\Domain\Katalog\Data\HasilTambahProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Enum\SumberPerubahanKatalog;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F-01 langkah 4: produk awal (contoh template atau tambah cepat) dalam satu transaksi.
 * - Idempoten per nama: nama yang sudah ada (tanpa beda huruf besar/kecil) atau muncul dua kali di masukan dilewati.
 * - BR-P04.3: batas `BatasSku` paket (`PemakaianSku`) diperiksa untuk semua produk baru sekaligus; ditolak = tidak
 *   ada yang tersimpan.
 * - F-03: setiap produk disimpan lewat `SimpanProduk` (sumber PanduanAwal): SKU otomatis, satuan dasar (jual & beli),
 *   dan harga dasar lewat Tim 2 sehingga `RiwayatHarga` ikut tercatat.
 * Urutan kunci: Tenant → Langganan (batas paket) → baris produk.
 */
final class TambahProdukCepat
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianSku $pemakaianSku,
        private readonly SimpanProduk $simpanProduk,
    ) {}

    /**
     * @param  list<DataProdukCepat>  $daftar
     */
    public function Jalankan(array $daftar): HasilTambahProduk
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $daftar): HasilTambahProduk {
            $this->penguncian->Kunci($idTenant);
            $namaAda = array_map(fn (mixed $nama): string => mb_strtolower(trim((string) $nama)), Produk::query()->pluck('Nama')->all());
            $baru = [];
            $dilewati = [];

            foreach ($daftar as $data) {
                $nama = trim($data->nama);
                $kunci = mb_strtolower($nama);

                if ($nama === '' || in_array($kunci, $namaAda, true)) {
                    $dilewati[] = $nama;

                    continue;
                }

                $namaAda[] = $kunci;
                $baru[] = $data;
            }

            if ($baru !== []) {
                $this->batasPaket->Pastikan($idTenant, 'BatasSku', fn (): int => $this->pemakaianSku->Hitung(), count($baru));
            }

            foreach ($baru as $data) {
                $this->simpanProduk->Jalankan(null, self::KeDataProduk($data));
            }

            return new HasilTambahProduk(array_map(fn (DataProdukCepat $data): string => trim($data->nama), $baru), $dilewati);
        });
    }

    private static function KeDataProduk(DataProdukCepat $data): DataProduk
    {
        return new DataProduk(
            uuid: (string) Str::ulid(),
            nama: trim($data->nama),
            namaStruk: null,
            sku: null,
            jenis: $data->jenis,
            idKategori: $data->idKategori,
            merek: null,
            idSatuanDasar: $data->idSatuanDasar,
            pelacakan: PelacakanProduk::Tidak,
            idKelompokPajak: $data->idKelompokPajak,
            hargaTermasukPajak: null,
            bolehMinus: null,
            tampilDiPos: true,
            tampilOnline: false,
            satuan: [new DataSatuanProduk(
                idProdukSatuan: null,
                idSatuan: $data->idSatuanDasar,
                konversiKeDasar: Kuantitas::Dari(1),
                defaultJual: true,
                defaultBeli: true,
                barcode: [],
                hargaAwal: [new DataBarisHarga(Kuantitas::Dari(1), $data->harga)],
            )],
            atributVarian: [],
            bolehUbahHarga: true,
            sumber: SumberPerubahanKatalog::PanduanAwal,
        );
    }
}
