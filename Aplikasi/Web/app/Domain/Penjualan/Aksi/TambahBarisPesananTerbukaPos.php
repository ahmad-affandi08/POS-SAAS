<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Pemenuhan\Aksi\KirimKeDapur;
use App\Domain\Pemenuhan\Data\DataKirimDapur;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use App\Domain\Penjualan\Enum\StatusBarisPesanan;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbukaDetail;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananTerbuka.Tambah` (F-07 mode meja fase 1): menambah baris (append-only, Uuid baris dari
 * perangkat) ke pesanan terbuka, satu ronde. Baris yang Uuid-nya sudah ada dilewati; semua sudah ada → `Duplikat`.
 * `KirimDapur` menandai baris baru terkirim dan membuat tiket dapur per stasiun di transaksi yang sama (F-10b).
 * Produk wajib dikenal & bisa dijual; harga di sini hanya tampilan (dihitung ulang saat dibayar).
 */
final class TambahBarisPesananTerbukaPos
{
    private const JENIS_TIDAK_BISA_DIJUAL = [JenisProduk::IndukVarian, JenisProduk::BahanBaku, JenisProduk::Konsinyasi];

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly KomposisiPenjualan $komposisi,
        private readonly MejaOutlet $meja,
        private readonly KirimKeDapur $kirimDapur,
    ) {}

    public function Jalankan(DataPesananTerbukaPos $data): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($data->waktu, 'DikirimPada');

        return DB::transaction(function () use ($data): StatusItemSinkron {
            $pesanan = $this->penjaga->CariUntukDiubah($data->uuidPesanan, $data->idOutlet);
            $sudah = PesananTerbukaDetail::query()->whereIn('Uuid', array_map(fn ($b): string => $b->uuid, $data->baris))->get(['Uuid', 'IdPesananTerbuka']);

            if ($sudah->contains(fn (PesananTerbukaDetail $d): bool => $d->IdPesananTerbuka !== $pesanan->Id)) {
                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik baris sudah dipakai pesanan lain.', 'Baris', 409);
            }

            $baru = array_values(array_filter($data->baris, fn ($b): bool => ! $sudah->contains('Uuid', $b->uuid)));

            if ($baru === []) {
                return StatusItemSinkron::Duplikat;
            }

            $this->penjaga->PastikanTerbuka($pesanan);
            $pelaku = $this->penjaga->CariPelaku($this->konteks->Wajib(), $data->uuidPengguna, $data->idOutlet);
            $produk = $this->komposisi->AmbilProduk(array_values(array_map(fn ($b): string => $b->uuidProduk, $baru)));
            $dibuat = [];

            foreach ($baru as $indeks => $b) {
                $p = $produk[$b->uuidProduk] ?? null;

                if ($p === null || $p->dihapus) {
                    throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk pada baris ke-'.($indeks + 1).' tidak ditemukan.', "Baris.{$indeks}.UuidProduk");
                }

                if (in_array($p->jenis, self::JENIS_TIDAK_BISA_DIJUAL, true)) {
                    throw new PelanggaranAturanBisnis('ProdukTidakBisaDijual', "Produk {$p->nama} berjenis {$p->jenis->AmbilLabel()} tidak bisa dipesan.", "Baris.{$indeks}.UuidProduk");
                }

                if ($b->uuidProdukSatuan !== null && ! isset($p->satuan[$b->uuidProdukSatuan])) {
                    throw new PelanggaranAturanBisnis('SatuanTidakDikenal', "Satuan jual {$p->nama} tidak ditemukan.", "Baris.{$indeks}.UuidProdukSatuan");
                }

                $dibuat[] = PesananTerbukaDetail::query()->create([
                    'Uuid' => $b->uuid,
                    'IdPesananTerbuka' => $pesanan->Id,
                    'IdProduk' => $p->id,
                    'UuidProduk' => $p->uuid,
                    'UuidProdukSatuan' => $b->uuidProdukSatuan,
                    'NamaProduk' => $p->nama,
                    'Jumlah' => $b->jumlah,
                    'HargaSatuan' => $b->hargaSatuan,
                    'HargaPilihan' => $b->hargaPilihan,
                    'Pilihan' => $b->pilihan === [] ? null : $b->pilihan,
                    'Catatan' => $b->catatan,
                    'Ronde' => $data->ronde,
                    'Status' => StatusBarisPesanan::Aktif,
                    'DikirimKeDapurPada' => $data->kirimDapur ? $data->waktu : null,
                    'IdPengguna' => $pelaku->id,
                    'IdPerangkat' => $data->idPerangkat,
                ]);
            }

            $pesanan->touch();

            if ($data->kirimDapur) {
                $this->kirimDapur->Jalankan(new DataKirimDapur(
                    idOutlet: $pesanan->IdOutlet,
                    idPesananTerbuka: $pesanan->Id,
                    idPenjualan: null,
                    nomorDokumen: $pesanan->Nomor,
                    namaMeja: $pesanan->IdMeja === null ? null : ($this->meja->AmbilPerId([$pesanan->IdMeja])[$pesanan->IdMeja]['Nama'] ?? null),
                    label: $pesanan->Label,
                    ronde: $data->ronde,
                    dikirimPada: $data->waktu,
                    baris: PenjagaPesananTerbuka::KeBarisDapur($dibuat),
                ));
            }

            return StatusItemSinkron::Diterima;
        });
    }
}
