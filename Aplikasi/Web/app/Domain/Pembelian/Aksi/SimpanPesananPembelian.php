<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Pembelian\Data\DataBarisTerhitung;
use App\Domain\Pembelian\Data\DataPesananPembelian;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Layanan\PenghitungPajakPembelian;
use App\Domain\Pembelian\Layanan\PenomorPembelian;
use App\Domain\Pembelian\Layanan\PenyelesaiBarisPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelianDetail;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah draf pesanan pembelian (F-04 fase 1, izin `pembelian.kelola`). PO baru langsung bernomor
 * `PO/{OUTLET}/{YYMM}/{SEQ4}` dan berstatus Draf; hanya Draf yang bisa diubah (`StatusTidakSesuai`). Pemasok & lokasi
 * stok harus aktif, 1–500 baris, satu baris per (produk, satuan). Total = Σ(Jumlah × Harga) − Σ diskon + PPN masukan
 * (pemasok PKP, tarif `TarifPajak` berlaku pada tanggal PO) + ongkir. Audit `pesanan-pembelian.simpan`.
 */
final class SimpanPesananPembelian
{
    public const MAKS_BARIS = 500;

    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly PenyelesaiBarisPembelian $penyelesai,
        private readonly PenghitungPajakPembelian $pajak,
        private readonly PenomorPembelian $penomor,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PemasokTidakDikenal, GudangTidakDikenal, BarisKosong, BarisGanda, StatusTidakSesuai, …
     */
    public function Jalankan(DataPesananPembelian $data, ?PesananPembelian $po = null): PesananPembelian
    {
        return DB::transaction(function () use ($data, $po): PesananPembelian {
            $terkunci = $po === null ? null : PesananPembelian::query()->whereKey($po->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci !== null && $terkunci->Status !== StatusPesananPembelian::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Pesanan pembelian berstatus {$terkunci->Status->AmbilLabel()} tidak bisa diubah.");
            }

            $pemasok = Pemasok::query()->where('Uuid', $data->uuidPemasok)->first();

            if ($pemasok === null || (! $pemasok->Aktif && $pemasok->Id !== $terkunci?->IdPemasok)) {
                throw new PelanggaranAturanBisnis('PemasokTidakDikenal', 'Pemasok tidak ditemukan atau nonaktif.', 'UuidPemasok');
            }

            $gudang = $this->infoGudang->AmbilBanyak([$data->idGudang])[$data->idGudang] ?? null;

            if ($gudang === null || (! $gudang->aktif && $gudang->id !== $terkunci?->IdGudang)) {
                throw new PelanggaranAturanBisnis('GudangTidakDikenal', 'Lokasi stok tidak ditemukan atau diarsipkan.', 'UuidGudang');
            }

            $baris = $this->HitungBaris($data);
            $bruto = array_reduce($baris, fn (Uang $t, DataBarisTerhitung $b): Uang => $t->Tambah($b->bruto), Uang::Nol());
            $diskon = array_reduce($baris, fn (Uang $t, DataBarisTerhitung $b): Uang => $t->Tambah($b->diskon), Uang::Nol());
            $ppn = $this->pajak->Hitung($pemasok->Pkp, $gudang->idOutlet, $data->tanggal, $bruto->Kurangi($diskon));

            if ($data->ongkir->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('HargaTidakValid', 'Ongkir tidak boleh negatif.', 'Ongkir');
            }

            $isian = [
                'IdPemasok' => $pemasok->Id,
                'IdGudang' => $gudang->id,
                'IdOutlet' => $gudang->idOutlet,
                'Tanggal' => $data->tanggal->toDateString(),
                'PerkiraanTiba' => $data->perkiraanTiba?->toDateString(),
                'TerminHari' => $data->terminHari ?? $pemasok->TerminHari,
                'Pkp' => $pemasok->Pkp,
                'TarifPpn' => $ppn->tarif,
                'PengaliDppPembilang' => $ppn->pengaliDppPembilang,
                'PengaliDppPenyebut' => $ppn->pengaliDppPenyebut,
                'PpnDikreditkan' => $ppn->dikreditkan,
                'Subtotal' => $bruto->KeString(),
                'Diskon' => $diskon->KeString(),
                'Pajak' => $ppn->pajak->KeString(),
                'Ongkir' => $data->ongkir->KeString(),
                'Total' => $bruto->Kurangi($diskon)->Tambah($ppn->pajak)->Tambah($data->ongkir)->KeString(),
                'Catatan' => $data->catatan === null || trim($data->catatan) === '' ? null : trim($data->catatan),
                'DiubahOleh' => $data->idPengguna,
            ];

            if ($terkunci === null) {
                $terkunci = PesananPembelian::query()->create([
                    ...$isian,
                    'Nomor' => $this->penomor->AmbilNomorLokasi(JenisDokumenBernomor::PesananPembelian, $data->tanggal, $gudang),
                    'Status' => StatusPesananPembelian::Draf,
                    'DibuatOleh' => $data->idPengguna,
                ]);
                $this->riwayat->Catat(PesananPembelian::JENIS_DOKUMEN, $terkunci->Id, null, StatusPesananPembelian::Draf->value, $data->idPengguna);
            } else {
                $terkunci->fill([...$isian, 'AlasanDitolak' => $terkunci->AlasanDitolak])->save();
                PesananPembelianDetail::query()->where('IdPesananPembelian', $terkunci->Id)->get()->each(fn (PesananPembelianDetail $d) => $d->delete());
            }

            foreach ($baris as $i => $b) {
                PesananPembelianDetail::query()->create([
                    'IdPesananPembelian' => $terkunci->Id,
                    'Urutan' => $i + 1,
                    'IdProduk' => $b->produk->id,
                    'NamaProduk' => mb_substr($b->produk->nama, 0, 150),
                    'Sku' => $b->produk->sku,
                    'IdProdukSatuan' => $b->idProdukSatuan,
                    'SimbolSatuan' => mb_substr($b->simbolSatuan, 0, 20),
                    'Konversi' => $b->konversi->KeString(),
                    'Jumlah' => $b->jumlah->KeString(),
                    'Harga' => $b->harga->KeString(),
                    'Diskon' => $b->diskon->KeString(),
                    'Subtotal' => $b->subtotal->KeString(),
                ]);
            }

            $this->audit->Catat('pesanan-pembelian.simpan', $terkunci, nilaiBaru: [
                'Nomor' => $terkunci->Nomor,
                'Pemasok' => $pemasok->Nama,
                'JumlahBaris' => count($baris),
                'Total' => $terkunci->Total,
            ], idPengguna: $data->idPengguna);

            return $terkunci;
        }, 3);
    }

    /**
     * @return list<DataBarisTerhitung>
     */
    private function HitungBaris(DataPesananPembelian $data): array
    {
        if ($data->baris === [] || count($data->baris) > self::MAKS_BARIS) {
            throw new PelanggaranAturanBisnis('BarisKosong', 'Isi 1 sampai '.self::MAKS_BARIS.' baris barang.', 'Baris');
        }

        $produk = $this->penyelesai->AmbilProduk(array_values(array_unique(array_map(fn ($b): string => $b->uuidProduk, $data->baris))));
        $hasil = [];
        $kunci = [];

        foreach ($data->baris as $i => $b) {
            $hitung = $this->penyelesai->Hitung($i, $produk, $b->uuidProduk, $b->uuidProdukSatuan, $b->jumlah, $b->harga, $b->diskon);
            $k = $hitung->produk->id.':'.($hitung->idProdukSatuan ?? 0);

            if (isset($kunci[$k])) {
                throw PenyelesaiBarisPembelian::Galat($i, 'Produk', 'BarisGanda', "{$hitung->produk->nama} dengan satuan {$hitung->simbolSatuan} sudah ada di baris lain.");
            }

            $kunci[$k] = true;
            $hasil[] = $hitung;
        }

        return $hasil;
    }
}
