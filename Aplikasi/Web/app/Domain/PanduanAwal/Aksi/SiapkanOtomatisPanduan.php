<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Data\DataPajakPanduan;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\PanduanAwal\Kueri\ProdukPanduan;
use App\Domain\PanduanAwal\Kueri\UsulanPajak;
use Illuminate\Support\Facades\DB;

/**
 * D-23 A "mulai jualan dalam 5 menit": satu klik di langkah Sektor menjalankan langkah yang biasanya diklik satu per
 * satu, memakai usulan yang sama dengan halamannya:
 * 1. terapkan template sektor (kategori, satuan, kelompok pajak, akun, metode bayar bawaan);
 * 2. konfirmasi pajak outlet sesuai usulan template & kota (dilewati bila kota belum diisi padahal PBJT diusulkan,
 *    atau pajak sudah pernah dikonfirmasi);
 * 3. tambahkan semua produk contoh template yang belum ada, dengan harga saran, sebatas sisa kuota SKU paket;
 * 4. tandai langkah Produk (bila ada produk) & Metode pembayaran (Tunai selalu tersedia) selesai.
 * Semua dalam satu transaksi; pemilik tetap bisa mengubah setiap langkah sesudahnya.
 */
final class SiapkanOtomatisPanduan
{
    public function __construct(
        private readonly TerapkanTemplateSektor $terapkan,
        private readonly KonfirmasiPajakPanduan $konfirmasiPajak,
        private readonly TambahkanProdukContoh $tambahContoh,
        private readonly TandaiLangkahPanduan $tandai,
        private readonly UsulanPajak $usulanPajak,
        private readonly ProdukPanduan $produkPanduan,
        private readonly OutletUtama $outletUtama,
    ) {}

    /**
     * @param  list<string>  $sektorLain
     * @return array{Template: string, PajakDikonfirmasi: bool, JumlahProduk: int, ProdukTerlewatKuota: int}
     */
    public function Jalankan(Outlet $outlet, string $kodeTemplate, array $sektorLain = []): array
    {
        return DB::transaction(function () use ($outlet, $kodeTemplate, $sektorLain): array {
            $hasilTemplate = $this->terapkan->Jalankan($outlet, $kodeTemplate, $sektorLain);
            $outlet->refresh();
            $ringkas = $this->outletUtama->CariAktifRingkas($outlet->Id);
            $pajakDikonfirmasi = false;
            $jumlahProduk = 0;
            $terlewatKuota = 0;

            if ($ringkas !== null) {
                $pajak = $this->usulanPajak->Ambil($ringkas);
                /** @var array{PungutPbjt: bool, BiayaLayananAktif: bool, PersenBiayaLayanan: string, HargaTermasukPajak: bool} $nilai */
                $nilai = $pajak['Nilai'];

                if (! $pajak['SudahDikonfirmasi'] && ! ($nilai['PungutPbjt'] && $outlet->KodeKota === null)) {
                    $this->konfirmasiPajak->Jalankan($outlet, new DataPajakPanduan(
                        pungutPbjt: $nilai['PungutPbjt'],
                        biayaLayananAktif: $nilai['BiayaLayananAktif'],
                        persenBiayaLayanan: $nilai['PersenBiayaLayanan'],
                        hargaTermasukPajak: $nilai['HargaTermasukPajak'],
                    ));
                    $pajakDikonfirmasi = true;
                }

                /** @var array{ProdukContoh: list<array{Nama: string, Harga: string, SudahAda: bool}>, JumlahProduk: int, BatasSku: array{Batas: int|null, Terpakai: int}} $produk */
                $produk = $this->produkPanduan->Ambil($ringkas);
                $batas = $produk['BatasSku'];
                $sisa = $batas['Batas'] === null ? PHP_INT_MAX : max(0, $batas['Batas'] - $batas['Terpakai']);
                $baru = array_values(array_filter($produk['ProdukContoh'], fn (array $c): bool => ! $c['SudahAda']));
                $masuk = array_slice($baru, 0, $sisa);
                $terlewatKuota = count($baru) - count($masuk);

                if ($masuk !== []) {
                    $hasil = $this->tambahContoh->Jalankan($outlet, array_map(fn (array $c): array => ['Nama' => $c['Nama'], 'Harga' => $c['Harga']], $masuk));
                    $jumlahProduk = count($hasil->ditambahkan);
                }

                if ($produk['JumlahProduk'] + $jumlahProduk > 0) {
                    $this->tandai->Jalankan(LangkahPanduan::Produk, StatusLangkahPanduan::Selesai, $outlet->Id);
                }
            }

            $this->tandai->Jalankan(LangkahPanduan::MetodePembayaran, StatusLangkahPanduan::Selesai, $outlet->Id);

            return [
                'Template' => $hasilTemplate->namaTemplate,
                'PajakDikonfirmasi' => $pajakDikonfirmasi,
                'JumlahProduk' => $jumlahProduk,
                'ProdukTerlewatKuota' => $terlewatKuota,
            ];
        });
    }
}
