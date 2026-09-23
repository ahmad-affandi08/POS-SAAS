<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataProdukCepat;
use App\Domain\Katalog\Data\HasilTambahProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 4: produk awal (contoh template atau tambah cepat) dalam satu transaksi.
 * - Idempoten per nama: nama yang sudah ada (tanpa beda huruf besar/kecil) atau muncul dua kali di masukan dilewati.
 * - BR-P04.3: batas `BatasSku` paket diperiksa untuk semua produk baru sekaligus; ditolak = tidak ada yang tersimpan.
 * - Satu produk = `Produk` + `ProdukSatuan` dasar (jual & beli) + `ProdukHarga` umum. Harga string desimal.
 * Urutan kunci: Tenant → Langganan (batas paket) → baris produk.
 */
final class TambahProdukCepat
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PencatatAudit $audit,
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
                $this->batasPaket->Pastikan($idTenant, 'BatasSku', fn (): int => Produk::query()->count(), count($baru));
            }

            foreach ($baru as $data) {
                $this->Buat($data);
            }

            return new HasilTambahProduk(array_map(fn (DataProdukCepat $data): string => trim($data->nama), $baru), $dilewati);
        });
    }

    private function Buat(DataProdukCepat $data): void
    {
        $produk = Produk::query()->create([
            'Nama' => trim($data->nama),
            'Jenis' => $data->jenis,
            'IdKategori' => $data->idKategori,
            'IdSatuanDasar' => $data->idSatuanDasar,
            'IdKelompokPajak' => $data->idKelompokPajak,
            'Aktif' => true,
            'TampilDiPos' => true,
        ]);
        $satuan = ProdukSatuan::query()->create([
            'IdProduk' => $produk->Id,
            'IdSatuan' => $data->idSatuanDasar,
            'KonversiKeDasar' => '1',
            'DefaultJual' => true,
            'DefaultBeli' => true,
        ]);
        ProdukHarga::query()->create([
            'IdProduk' => $produk->Id,
            'IdProdukSatuan' => $satuan->Id,
            'IdDaftarHarga' => null,
            'JumlahMinimum' => '1',
            'Harga' => $data->harga->KeString(),
        ]);

        $this->audit->Catat('produk.buat', $produk, nilaiBaru: [
            'Nama' => $produk->Nama,
            'Harga' => $data->harga->KeString(),
            'IdKategori' => $produk->IdKategori,
            'Jenis' => $produk->Jenis->value,
        ]);
    }
}
