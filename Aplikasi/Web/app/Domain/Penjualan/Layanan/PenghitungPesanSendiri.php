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
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
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
use App\Domain\Penjualan\Kueri\BelanjaPelanggan;
use App\Domain\Promo\Kueri\PemakaianPromo;
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
 * - tanpa pembulatan tunai (metode bayar belum diketahui) sehingga `Pembulatan` selalu 0;
 * - ongkir (F-17 bagian 3, hanya checkout toko online): `$biayaKirim` ikut masuk mesin sehingga promo gratis ongkir
 *   dan pajak atas ongkir (`KenaBiayaKirim`) dihitung dengan aturan yang sama dengan kasir. Kunci `BiayaKirim` &
 *   `DiskonKirim` di `Perkiraan` hanya ada bila ongkir dikirim, jadi keluaran self-order meja tidak berubah;
 * - pembeli toko online yang sudah masuk (F-17 bagian 3, `$idPelanggan`): harga tier dan promo bersyarat pelanggan
 *   (tier, ulang tahun, transaksi pertama, batas per pelanggan) dihitung dengan data pelanggan yang sama dengan yang
 *   dipakai server saat memeriksa penjualan POS (`PemeriksaPromoPenjualan`), sehingga total pesanan sama dengan yang
 *   kasir tagih saat pesanan itu dimuat bersama pelanggannya;
 * - voucher berkode di checkout toko online (v3.46, `$voucher` = Uuid promo yang sudah diperiksa domain Promo): promo
 *   wajib voucher itu ikut dinilai mesin promo yang sama dengan kasir, yang juga memuat voucher pesanan.
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
        private readonly IdentitasPelanggan $pelanggan,
        private readonly BelanjaPelanggan $belanja,
        private readonly PemakaianPromo $pemakaian,
        private readonly MesinKalkulasi $mesin = new MesinKalkulasi,
        private readonly MesinPromo $mesinPromo = new MesinPromo,
    ) {}

    /**
     * @param  list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian?: string|null}>  $baris
     * @param  list<string>  $voucher  Uuid promo voucher yang sudah diperiksa (F-17 v3.46, checkout toko online)
     * @return array{Baris: list<array{UuidProduk: string, UuidProdukSatuan: string, NamaProduk: string, UuidProdukInduk: string|null, NamaVarian: string|null, Jumlah: Kuantitas, HargaSatuan: Uang, HargaPilihan: Uang, Total: Uang, Pilihan: list<array{UuidPilihan: string, Nama: string, Harga: string}>, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null, UuidKategori: string|null}>, Subtotal: Uang, Perkiraan: array{BiayaKirim?: Uang, DiskonKirim?: Uang, Diskon: Uang, BiayaLayanan: Uang, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: Uang}>, PajakTermasukHarga: Uang, Pembulatan: Uang, Total: Uang}}
     */
    public function Hitung(DataKonteksPesanSendiri $konteks, array $baris, KanalPenjualan $kanal = KanalPenjualan::MakanDiTempat, bool $tampilOnline = false, ?Uang $biayaKirim = null, ?int $idPelanggan = null, array $voucher = []): array
    {
        $berharga = $this->menu->HitungBaris($konteks->idOutlet, $baris, $kanal, $tampilOnline, $this->pelanggan->AmbilKodeTier($idPelanggan));
        $hasil = [];
        $subtotal = Uang::Nol();

        foreach ($berharga as $i => $b) {
            $jumlah = Kuantitas::Dari($baris[$i]['Jumlah']);
            $total = $b['HargaSatuan']->Tambah($b['HargaPilihan'])->Kali($jumlah->KeDesimal());
            $subtotal = $subtotal->Tambah($total);
            $hasil[] = [...$b, 'Jumlah' => $jumlah, 'Total' => $total];
        }

        return ['Baris' => $hasil, 'Subtotal' => $subtotal, 'Perkiraan' => $this->HitungPerkiraan($konteks, $hasil, $subtotal, $kanal, $biayaKirim, $idPelanggan, $voucher)];
    }

    /**
     * Bentuk string (JSON/penyimpanan) dari `Perkiraan`.
     *
     * @param  array{BiayaKirim?: Uang, DiskonKirim?: Uang, Diskon: Uang, BiayaLayanan: Uang, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: Uang}>, PajakTermasukHarga: Uang, Pembulatan: Uang, Total: Uang}  $perkiraan
     * @return array{BiayaKirim?: string, DiskonKirim?: string, Diskon: string, BiayaLayanan: string, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: string}>, PajakTermasukHarga: string, Pembulatan: string, Total: string}
     */
    public static function KeLarik(array $perkiraan): array
    {
        return [
            ...(isset($perkiraan['BiayaKirim'], $perkiraan['DiskonKirim']) ? ['BiayaKirim' => $perkiraan['BiayaKirim']->KeString(), 'DiskonKirim' => $perkiraan['DiskonKirim']->KeString()] : []),
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
     * @param  list<string>  $voucher
     * @return array{BiayaKirim?: Uang, DiskonKirim?: Uang, Diskon: Uang, BiayaLayanan: Uang, Pajak: list<array{Kode: string, Nama: string, Tarif: string, Jumlah: Uang}>, PajakTermasukHarga: Uang, Pembulatan: Uang, Total: Uang}
     */
    private function HitungPerkiraan(DataKonteksPesanSendiri $konteks, array $baris, Uang $subtotal, KanalPenjualan $kanal, ?Uang $biayaKirim = null, ?int $idPelanggan = null, array $voucher = []): array
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

                $pajakDokumen[$jenis['Kode']] ??= new DataPajakKalkulasi($jenis['Kode'], $tarif->tarif, $jenis['DasarPengenaan'], $tarif->pengaliDppPembilang, $tarif->pengaliDppPenyebut, $jenis['KenaBiayaKirim']);
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
            biayaKirim: $biayaKirim,
        );
        $hasil = $this->TerapkanPromo($konteks, $dasar, $baris, $kanal, $idPelanggan, $tanggal->toDateString(), $voucher);

        return [
            ...($biayaKirim === null ? [] : ['BiayaKirim' => $hasil->biayaKirim, 'DiskonKirim' => $hasil->diskonKirim]),
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
     * Promo otomatis yang berlaku; tamu tanpa identitas pelanggan, pembeli yang masuk dengan data pelanggannya.
     * Tanpa promo = hasil mesin kalkulasi biasa.
     *
     * @param  list<array{UuidProduk: string, UuidKategori: string|null}>  $baris
     * @param  list<string>  $voucher
     */
    private function TerapkanPromo(DataKonteksPesanSendiri $konteks, DataKalkulasi $dasar, array $baris, KanalPenjualan $kanal, ?int $idPelanggan, string $tanggalBisnis, array $voucher = []): HasilKalkulasi
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
                $kanal,
                $this->pelanggan->AmbilKodeTier($idPelanggan),
                voucher: $voucher,
                berpelanggan: $idPelanggan !== null,
                tanggalLahir: $this->pelanggan->AmbilTanggalLahir($idPelanggan),
                jumlahTransaksiPelanggan: $idPelanggan === null ? null : $this->belanja->HitungTransaksiSebelum($idPelanggan, $sekarang),
                pemakaianPelanggan: $idPelanggan === null ? [] : $this->pemakaian->HitungPerPelanggan($idPelanggan, $tanggalBisnis),
            ),
            $this->promo->AmbilMode(),
        )->hasil;
    }
}
