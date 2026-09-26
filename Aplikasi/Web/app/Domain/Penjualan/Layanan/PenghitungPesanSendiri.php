<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\MenuPesanSendiri;
use App\Domain\Organisasi\Data\DataProfilPajakOutlet;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Penjualan\Data\DataKonteksPesanSendiri;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Kalkulasi\BarisPromo;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\HasilKalkulasi;
use App\Domain\Penjualan\Kalkulasi\KonteksPromo;
use App\Domain\Penjualan\Kalkulasi\MesinKalkulasi;
use App\Domain\Penjualan\Kalkulasi\MesinPromo;
use App\Domain\Promo\Kueri\PromoBerlaku;
use Carbon\CarbonImmutable;

/**
 * F-17: harga baris pesanan tamu dihitung ulang server dari katalog (harga kanal `MakanDiTempat` + harga pilihan);
 * harga dari peramban tidak pernah dipakai. Total baris = (HargaSatuan + HargaPilihan) × Jumlah, Subtotal = Σ Total.
 *
 * Estimasi total (PRD v2.06) memakai mesin kalkulasi F-07a yang sama dengan kasir dan aturan pajak yang sama dengan
 * aplikasi kasir & pemeriksaan server saat menerima penjualan POS (`PemeriksaSnapshotPengaturanPenjualan`):
 * - pajak per baris dari kelompok pajak produk menurut kategori jenis pajak (Ppn hanya bila outlet PKP, Pbjt hanya bila
 *   memungut PBJT, Lainnya selalu), tarif dari `TarifPajak` terbit yang berlaku pada tanggal bisnis outlet (kota outlet
 *   untuk pajak daerah; tanpa tarif = tidak dihitung, CLAUDE.md #12), dasar pengenaan dari detail kelompok pajak;
 * - harga termasuk pajak: `Produk.HargaTermasukPajak` ?? profil pajak outlet; biaya layanan outlet (0 bila tidak aktif);
 * - promo otomatis tanpa pelanggan (kanal `MakanDiTempat`, outlet, jam lokal outlet): promo wajib voucher, tier,
 *   metode bayar, ulang tahun, dan transaksi pertama tidak berlaku karena tamu belum dikenal & belum memilih bayar;
 * - tanpa pembulatan tunai (metode bayar belum diketahui) sehingga `Pembulatan` selalu 0.
 * Hasilnya perkiraan; tagihan akhir tetap dihitung kasir.
 */
final class PenghitungPesanSendiri
{
    public const CATATAN = 'Perkiraan. Total akhir mengikuti tagihan di kasir (promo, pembulatan, metode bayar).';

    public function __construct(
        private readonly MenuPesanSendiri $menu,
        private readonly ProfilPajakOutlet $profilPajak,
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PromoBerlaku $promo,
        private readonly MesinKalkulasi $mesin = new MesinKalkulasi,
        private readonly MesinPromo $mesinPromo = new MesinPromo,
    ) {}

    /**
     * @param  list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian?: string|null}>  $baris
     * @return array{Baris: list<array{UuidProduk: string, UuidProdukSatuan: string, NamaProduk: string, UuidProdukInduk: string|null, NamaVarian: string|null, Jumlah: Kuantitas, HargaSatuan: Uang, HargaPilihan: Uang, Total: Uang, Pilihan: list<array{UuidPilihan: string, Nama: string, Harga: string}>, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null, UuidKategori: string|null}>, Subtotal: Uang, Perkiraan: array{Diskon: Uang, BiayaLayanan: Uang, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: Uang}>, PajakTermasukHarga: Uang, Pembulatan: Uang, Total: Uang}}
     */
    public function Hitung(DataKonteksPesanSendiri $konteks, array $baris): array
    {
        $berharga = $this->menu->HitungBaris($konteks->idOutlet, $baris);
        $hasil = [];
        $subtotal = Uang::Nol();

        foreach ($berharga as $i => $b) {
            $jumlah = Kuantitas::Dari($baris[$i]['Jumlah']);
            $total = $b['HargaSatuan']->Tambah($b['HargaPilihan'])->Kali($jumlah->KeDesimal());
            $subtotal = $subtotal->Tambah($total);
            $hasil[] = [...$b, 'Jumlah' => $jumlah, 'Total' => $total];
        }

        return ['Baris' => $hasil, 'Subtotal' => $subtotal, 'Perkiraan' => $this->HitungPerkiraan($konteks, $hasil, $subtotal)];
    }

    /**
     * Bentuk string (JSON/penyimpanan) dari `Perkiraan`.
     *
     * @param  array{Diskon: Uang, BiayaLayanan: Uang, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: Uang}>, PajakTermasukHarga: Uang, Pembulatan: Uang, Total: Uang}  $perkiraan
     * @return array{Diskon: string, BiayaLayanan: string, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: string}>, PajakTermasukHarga: string, Pembulatan: string, Total: string}
     */
    public static function KeLarik(array $perkiraan): array
    {
        return [
            'Diskon' => $perkiraan['Diskon']->KeString(),
            'BiayaLayanan' => $perkiraan['BiayaLayanan']->KeString(),
            'Pajak' => array_map(fn (array $p): array => [...$p, 'Jumlah' => $p['Jumlah']->KeString()], $perkiraan['Pajak']),
            'PajakTermasukHarga' => $perkiraan['PajakTermasukHarga']->KeString(),
            'Pembulatan' => $perkiraan['Pembulatan']->KeString(),
            'Total' => $perkiraan['Total']->KeString(),
        ];
    }

    /**
     * @param  list<array{UuidProduk: string, Jumlah: Kuantitas, HargaSatuan: Uang, HargaPilihan: Uang, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null, UuidKategori: string|null}>  $baris
     * @return array{Diskon: Uang, BiayaLayanan: Uang, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: Uang}>, PajakTermasukHarga: Uang, Pembulatan: Uang, Total: Uang}
     */
    private function HitungPerkiraan(DataKonteksPesanSendiri $konteks, array $baris, Uang $subtotal): array
    {
        if ($baris === []) {
            return ['Diskon' => Uang::Nol(), 'BiayaLayanan' => Uang::Nol(), 'Pajak' => [], 'PajakTermasukHarga' => Uang::Nol(), 'Pembulatan' => Uang::Nol(), 'Total' => $subtotal];
        }

        $profil = $this->profilPajak->Ambil($konteks->idOutlet) ?? new DataProfilPajakOutlet(false, false, false, '0.00', false);
        $tanggal = $this->tanggalBisnis->Hitung($konteks->idOutlet);
        $pajakKelompok = $this->kelompokPajak->AmbilJenisPajakPerKelompok(array_values(array_unique(array_filter(array_column($baris, 'IdKelompokPajak'), 'is_int'))));
        $pajakDokumen = [];
        $namaPajak = [];
        $kodeBaris = [];

        foreach ($baris as $b) {
            $kode = [];

            foreach ($b['IdKelompokPajak'] === null ? [] : ($pajakKelompok[$b['IdKelompokPajak']] ?? []) as $jenis) {
                $berlaku = match ($jenis['Kategori']) {
                    KategoriJenisPajak::Ppn => $profil->pkp,
                    KategoriJenisPajak::Pbjt => $profil->pungutPbjt,
                    KategoriJenisPajak::Lainnya => true,
                };
                $tarif = $berlaku ? $this->tarifBerlaku->CariDataOutlet($jenis['Kode'], $konteks->kodeKota, $tanggal) : null;

                if ($tarif === null) {
                    continue;
                }

                if (! in_array($jenis['Kode'], $kode, true)) {
                    $kode[] = $jenis['Kode'];
                }

                $pajakDokumen[$jenis['Kode']] ??= new DataPajakKalkulasi($jenis['Kode'], $tarif->tarif, $jenis['DasarPengenaan'], $tarif->pengaliDppPembilang, $tarif->pengaliDppPenyebut);
                $namaPajak[$jenis['Kode']] ??= $jenis['Nama'];
            }

            $kodeBaris[] = $kode;
        }

        $dasar = new DataKalkulasi(
            hargaTermasukPajak: $profil->hargaTermasukPajak,
            baris: array_values(array_map(fn (array $b, array $kode): DataBarisKalkulasi => new DataBarisKalkulasi(
                $b['Jumlah'],
                $b['HargaSatuan'],
                $b['HargaPilihan'],
                $b['HargaTermasukPajak'],
                $kode,
            ), $baris, $kodeBaris)),
            pajak: array_values($pajakDokumen),
            persenBiayaLayanan: $profil->biayaLayananAktif ? $profil->persenBiayaLayanan : '0',
        );
        $hasil = $this->TerapkanPromo($konteks, $dasar, $baris);

        return [
            'Diskon' => $hasil->totalDiskon,
            'BiayaLayanan' => $hasil->biayaLayanan,
            'Pajak' => array_values(array_map(fn (string $kode): array => [
                'Kode' => $kode,
                'Nama' => $namaPajak[$kode],
                'Tarif' => (string) $pajakDokumen[$kode]->tarif->strippedOfTrailingZeros(),
                'Jumlah' => $hasil->pajak[$kode]->jumlah,
            ], array_keys($hasil->pajak))),
            'PajakTermasukHarga' => $hasil->totalPajak->Kurangi($hasil->totalPajakEksklusif),
            'Pembulatan' => $hasil->pembulatan,
            'Total' => $hasil->totalAkhir,
        ];
    }

    /**
     * Promo otomatis yang berlaku untuk tamu tanpa identitas pelanggan; tanpa promo = hasil mesin kalkulasi biasa.
     *
     * @param  list<array{UuidProduk: string, UuidKategori: string|null}>  $baris
     */
    private function TerapkanPromo(DataKonteksPesanSendiri $konteks, DataKalkulasi $dasar, array $baris): HasilKalkulasi
    {
        $definisi = $this->promo->AmbilDefinisi();

        if ($definisi === []) {
            return $this->mesin->Hitung($dasar);
        }

        $sekarang = CarbonImmutable::now();

        return $this->mesinPromo->Terapkan(
            $dasar,
            array_values(array_map(fn (array $b): BarisPromo => new BarisPromo($b['UuidProduk'], $b['UuidKategori']), $baris)),
            $definisi,
            new KonteksPromo(
                $sekarang->utc(),
                $sekarang->setTimezone($konteks->zonaWaktu),
                $konteks->uuidOutlet === '' ? null : $konteks->uuidOutlet,
                KanalPenjualan::MakanDiTempat,
            ),
            $this->promo->AmbilMode(),
        )->hasil;
    }
}
