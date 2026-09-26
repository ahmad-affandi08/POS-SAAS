<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kalkulasi;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisAksiPromo;
use App\Domain\Penjualan\Enum\JenisKondisiPromo;
use App\Domain\Penjualan\Enum\JenisUlangTahunPromo;
use App\Domain\Penjualan\Enum\ModeResolusiPromo;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Mesin promo F-16c bagian 1 (PRD F-16 Promo Engine, "Rincian F-16c"). **Murni** (tanpa DB), satu algoritma dengan
 * `MesinPromo` di `Paket/MesinKasir`; keduanya wajib lolos test vector `Spesifikasi/VektorUjiKalkulasi/Promo/`.
 *
 * 1. Promo berlaku bila kuota belum habis, waktu di `[mulaiPada, selesaiPada)`, hari & jam lokal, outlet, kanal, dan
 *    tier cocok, voucher sudah divalidasi (promo wajib voucher), subtotal awal (setelah diskon manual baris) ≥ minimal,
 *    dan jumlah barang kondisi cukup. Bagian 3: semua pembayaran memakai metode promo, hari ulang tahun pelanggan,
 *    transaksi pertama pelanggan, dan pemakaian pelanggan di bawah batas (per hari/selama promo).
 * 2. `PrioritasKetat`: urut prioritas (besar dulu, seri menurut kode); eksklusif hanya bila belum ada yang terpilih dan
 *    menghentikan evaluasi. `Terbaik`: semua non-eksklusif bersama vs tiap eksklusif sendiri, potongan terbesar menang
 *    (seri: kandidat lebih awal).
 * 3. Promo barang dulu (dibatasi sisa netto baris), lalu promo pesanan (dari subtotal setelah promo barang, dibatasi
 *    sisa subtotal). Semua potongan menjadi nominal lalu dihitung `MesinKalkulasi`.
 * 4. Bagian 4: promo `PoinBerlipat` tidak ikut resolusi potongan (tidak bersaing dengan promo harga, `Eksklusif`-nya
 *    diabaikan); dari yang berlaku dipilih pengali terbesar (seri: urutan prioritas), tidak dikalikan bertumpuk.
 */
final class MesinPromo
{
    public function __construct(
        private readonly MesinKalkulasi $mesin = new MesinKalkulasi,
        private readonly PengalokasiSisaTerbesar $pengalokasi = new PengalokasiSisaTerbesar,
    ) {}

    /**
     * @param  list<BarisPromo>  $barisPromo
     * @param  list<DefinisiPromo>  $promo
     */
    public function Terapkan(DataKalkulasi $dasar, array $barisPromo, array $promo, KonteksPromo $konteks, ModeResolusiPromo $mode = ModeResolusiPromo::Terbaik): HasilPromo
    {
        if (count($barisPromo) !== count($dasar->baris)) {
            throw new InvalidArgumentException('Jumlah baris promo harus sama dengan baris kalkulasi.');
        }

        $hasilDasar = $this->mesin->Hitung($dasar);
        $sisaAwal = array_map(fn (HasilBarisKalkulasi $b): Uang => $b->bruto->Kurangi($b->diskon), $hasilDasar->baris);
        $bruto = array_map(fn (HasilBarisKalkulasi $b): Uang => $b->bruto, $hasilDasar->baris);

        $berlaku = array_values(array_filter(
            $promo,
            fn (DefinisiPromo $p): bool => self::CekBerlaku($p, $konteks, $hasilDasar->subtotal) && $this->CekKondisi($p, $dasar, $barisPromo),
        ));
        usort($berlaku, [self::class, 'BandingkanUrutan']);
        $poinBerlipat = null;

        foreach ($berlaku as $p) {
            if ($p->aksi === JenisAksiPromo::PoinBerlipat && $p->AmbilPengali()->isGreaterThan($poinBerlipat?->AmbilPengali() ?? BigDecimal::one())) {
                $poinBerlipat = $p;
            }
        }

        $berlaku = array_values(array_filter($berlaku, fn (DefinisiPromo $p): bool => $p->aksi !== JenisAksiPromo::PoinBerlipat));
        $evaluasi = fn (array $daftar): array => $this->Evaluasi(array_values($daftar), $dasar, $barisPromo, $sisaAwal, $bruto);

        if ($mode === ModeResolusiPromo::PrioritasKetat) {
            $daftar = [];

            foreach ($berlaku as $p) {
                if ($evaluasi([$p]) === []) {
                    continue;
                }

                if ($p->eksklusif) {
                    if ($daftar === []) {
                        $daftar[] = $p;

                        break;
                    }

                    continue;
                }

                $daftar[] = $p;
            }

            $terpilih = $evaluasi($daftar);
        } else {
            $kandidat = [$evaluasi(array_values(array_filter($berlaku, fn (DefinisiPromo $p): bool => ! $p->eksklusif)))];

            foreach ($berlaku as $p) {
                if ($p->eksklusif) {
                    $kandidat[] = $evaluasi([$p]);
                }
            }

            $terpilih = $kandidat[0];

            foreach (array_slice($kandidat, 1) as $k) {
                if (self::HitungTotal($k)->Bandingkan(self::HitungTotal($terpilih)) > 0) {
                    $terpilih = $k;
                }
            }
        }

        $data = self::SusunData($dasar, $terpilih);

        return new HasilPromo($terpilih, $data, $this->mesin->Hitung($data), $poinBerlipat);
    }

    public static function BandingkanUrutan(DefinisiPromo $a, DefinisiPromo $b): int
    {
        return $b->prioritas <=> $a->prioritas ?: strcmp($a->kode, $b->kode);
    }

    /**
     * @param  list<PromoTerpakai>  $daftar
     */
    public static function HitungTotal(array $daftar): Uang
    {
        return array_reduce($daftar, fn (Uang $t, PromoTerpakai $p): Uang => $t->Tambah($p->HitungTotal()), Uang::Nol());
    }

    public static function CekBerlaku(DefinisiPromo $p, KonteksPromo $k, Uang $subtotalAwal): bool
    {
        if ($p->kuotaTersisa !== null && $p->kuotaTersisa <= 0) {
            return false;
        }

        if ($p->mulaiPada !== null && $k->waktu->lessThan($p->mulaiPada)) {
            return false;
        }

        if ($p->selesaiPada !== null && ! $k->waktu->lessThan($p->selesaiPada)) {
            return false;
        }

        if ($p->hari !== [] && ! in_array((int) $k->waktuLokal->format('N'), $p->hari, true)) {
            return false;
        }

        if ($p->jamMulai !== null || $p->jamSelesai !== null) {
            $menit = (int) $k->waktuLokal->format('G') * 60 + (int) $k->waktuLokal->format('i');
            $mulai = $p->jamMulai ?? 0;
            $selesai = $p->jamSelesai ?? 1440;
            $cocok = $mulai < $selesai ? $menit >= $mulai && $menit < $selesai : $menit >= $mulai || $menit < $selesai;

            if (! $cocok) {
                return false;
            }
        }

        if ($p->uuidOutlet !== [] && ! in_array($k->uuidOutlet, $p->uuidOutlet, true)) {
            return false;
        }

        if ($p->kanal !== [] && ! in_array($k->kanal, $p->kanal, true)) {
            return false;
        }

        if ($p->tier !== [] && ! in_array($k->tier, $p->tier, true)) {
            return false;
        }

        if ($p->wajibVoucher && ! in_array($p->uuid, $k->voucher, true)) {
            return false;
        }

        if ($p->metodeBayar !== [] && ($k->metodeBayar === null || $k->metodeBayar === [] || array_diff($k->metodeBayar, $p->metodeBayar) !== [])) {
            return false;
        }

        if ($p->ulangTahun !== null && ! self::CekUlangTahun($p, $k)) {
            return false;
        }

        if ($p->transaksiPertama && (! $k->berpelanggan || $k->jumlahTransaksiPelanggan !== 0)) {
            return false;
        }

        if ($p->batasPerPelanggan !== null) {
            $pakai = $k->pemakaianPelanggan[$p->uuid] ?? ['Hari' => 0, 'Promo' => 0];

            if (! $k->berpelanggan || $pakai[$p->periodeBatasPelanggan->value] >= $p->batasPerPelanggan) {
                return false;
            }
        }

        return $subtotalAwal->Bandingkan($p->AmbilMinimalSubtotal()) >= 0;
    }

    /**
     * Tanggal lokal outlet vs tanggal lahir pelanggan (tahun lahir diabaikan; 29 Februari = 28 Februari di tahun bukan
     * kabisat). `Rentang` memeriksa ulang tahun tahun lalu, tahun ini, dan tahun depan agar ± N hari melewati tahun baru.
     */
    public static function CekUlangTahun(DefinisiPromo $p, KonteksPromo $k): bool
    {
        if ($k->tanggalLahir === null || preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $k->tanggalLahir, $m) !== 1) {
            return false;
        }

        $bulan = (int) $m[1];
        $tanggal = (int) $m[2];
        $hariIni = $k->waktuLokal->startOfDay();

        if ($p->ulangTahun === JenisUlangTahunPromo::Bulan) {
            return (int) $hariIni->format('n') === $bulan;
        }

        $jarak = $p->ulangTahun === JenisUlangTahunPromo::Rentang ? $p->hariUlangTahun : 0;

        foreach ([-1, 0, 1] as $geser) {
            $tahun = (int) $hariIni->format('Y') + $geser;
            $hari = $bulan === 2 && $tanggal === 29 && ! checkdate(2, 29, $tahun) ? 28 : $tanggal;
            $ulangTahun = $hariIni->setDate($tahun, $bulan, $hari);

            if (abs((int) $hariIni->diffInDays($ulangTahun, false)) <= $jarak) {
                return true;
            }
        }

        return false;
    }

    /**
     * Masukan kalkulasi baru: potongan promo nominal ditambahkan setelah potongan yang sudah ada.
     *
     * @param  list<PromoTerpakai>  $terpakai
     */
    public static function SusunData(DataKalkulasi $dasar, array $terpakai): DataKalkulasi
    {
        $baris = [];

        foreach ($dasar->baris as $i => $b) {
            $potongan = $b->potongan;

            foreach ($terpakai as $t) {
                if (isset($t->diskonBaris[$i])) {
                    $potongan[] = DataPotongan::BuatNominal($t->diskonBaris[$i]);
                }
            }

            $baris[] = new DataBarisKalkulasi($b->jumlah, $b->hargaSatuan, $b->hargaPilihan, $b->hargaTermasukPajak, $b->kodePajak, $potongan);
        }

        $potonganPesanan = $dasar->potonganPesanan;

        foreach ($terpakai as $t) {
            if (! $t->diskonPesanan->BernilaiNol()) {
                $potonganPesanan[] = DataPotongan::BuatNominal($t->diskonPesanan);
            }
        }

        return new DataKalkulasi(
            $dasar->hargaTermasukPajak,
            $baris,
            $dasar->pajak,
            $dasar->persenBiayaLayanan,
            $dasar->pembulatanTunai,
            $potonganPesanan,
            $dasar->pembayaran,
            $dasar->tukarPoin,
        );
    }

    /**
     * @param  list<BarisPromo>  $barisPromo
     * @return list<int>
     */
    private function AmbilIndeksKondisi(DefinisiPromo $p, array $barisPromo): array
    {
        $indeks = [];

        foreach ($barisPromo as $i => $b) {
            $cocok = match ($p->kondisi) {
                JenisKondisiPromo::Semua => true,
                JenisKondisiPromo::Produk => in_array($b->uuidProduk, $p->uuidKondisi, true),
                JenisKondisiPromo::Kategori => $b->uuidKategori !== null && in_array($b->uuidKategori, $p->uuidKondisi, true),
            };

            if ($cocok) {
                $indeks[] = $i;
            }
        }

        return $indeks;
    }

    /**
     * @param  list<BarisPromo>  $barisPromo
     */
    private function CekKondisi(DefinisiPromo $p, DataKalkulasi $dasar, array $barisPromo): bool
    {
        $indeks = $this->AmbilIndeksKondisi($p, $barisPromo);

        if ($indeks === []) {
            return false;
        }

        $total = BigDecimal::zero();

        foreach ($indeks as $i) {
            $total = $total->plus($dasar->baris[$i]->jumlah->KeDesimal());
        }

        return $total->isGreaterThanOrEqualTo($p->AmbilJumlahMinimal()->KeDesimal());
    }

    /**
     * Satuan utuh barang kondisi (baris berjumlah pecahan dilewati), urut harga satuan terbesar lalu indeks baris.
     *
     * @param  list<BarisPromo>  $barisPromo
     * @return list<array{0: int, 1: Uang}>
     */
    private function AmbilSatuan(DefinisiPromo $p, DataKalkulasi $dasar, array $barisPromo): array
    {
        $satuan = [];

        foreach ($this->AmbilIndeksKondisi($p, $barisPromo) as $i) {
            $jumlah = $dasar->baris[$i]->jumlah->KeDesimal();

            if ($jumlah->getFractionalPart()->isZero()) {
                $harga = $dasar->baris[$i]->hargaSatuan->Tambah($dasar->baris[$i]->hargaPilihan ?? Uang::Nol());

                for ($n = 0; $n < $jumlah->toBigInteger()->toInt(); $n++) {
                    $satuan[] = [$i, $harga];
                }
            }
        }

        usort($satuan, fn (array $a, array $b): int => $b[1]->Bandingkan($a[1]) ?: $a[0] <=> $b[0]);

        return $satuan;
    }

    /**
     * Potongan mentah promo barang per indeks baris (belum dibatasi sisa baris).
     *
     * @param  list<BarisPromo>  $barisPromo
     * @param  list<Uang>  $bruto
     * @return array<int, Uang>
     */
    private function HitungDiskonBarang(DefinisiPromo $p, DataKalkulasi $dasar, array $barisPromo, array $bruto): array
    {
        $hasil = [];
        $tambah = function (int $i, Uang $nilai) use (&$hasil): void {
            $hasil[$i] = ($hasil[$i] ?? Uang::Nol())->Tambah($nilai);
        };

        switch ($p->aksi) {
            case JenisAksiPromo::DiskonPersenItem:
                foreach ($this->AmbilIndeksKondisi($p, $barisPromo) as $i) {
                    $tambah($i, $bruto[$i]->Kali(($p->persen ?? BigDecimal::zero())->withPointMovedLeft(2)));
                }

                break;
            case JenisAksiPromo::DiskonTetapItem:
                foreach ($this->AmbilIndeksKondisi($p, $barisPromo) as $i) {
                    $tambah($i, ($p->jumlah ?? Uang::Nol())->Kali($dasar->baris[$i]->jumlah->KeDesimal()));
                }

                break;
            case JenisAksiPromo::HargaSpesial:
                foreach ($this->AmbilIndeksKondisi($p, $barisPromo) as $i) {
                    $selisih = $bruto[$i]->Kurangi(($p->harga ?? Uang::Nol())->Kali($dasar->baris[$i]->jumlah->KeDesimal()));

                    if (! $selisih->BernilaiNegatif()) {
                        $tambah($i, $selisih);
                    }
                }

                break;
            case JenisAksiPromo::BeliXGratisY:
                $satuan = $this->AmbilSatuan($p, $dasar, $barisPromo);
                $beli = $p->beli ?? 0;
                $ukuran = $beli + ($p->gratis ?? 0);
                $set = $ukuran <= 0 ? 0 : intdiv(count($satuan), $ukuran);
                $set = $p->batasPerTransaksi !== null ? min($set, $p->batasPerTransaksi) : $set;

                for ($s = 0; $s < $set; $s++) {
                    for ($n = $s * $ukuran + $beli; $n < ($s + 1) * $ukuran; $n++) {
                        $tambah($satuan[$n][0], $satuan[$n][1]->Kali($p->AmbilPersenGratis()->withPointMovedLeft(2)));
                    }
                }

                break;
            case JenisAksiPromo::BundelHargaTetap:
                $satuan = $this->AmbilSatuan($p, $dasar, $barisPromo);
                $ukuran = $p->AmbilJumlahMinimal()->KeDesimal()->toBigInteger()->toInt();
                $set = $ukuran <= 0 ? 0 : intdiv(count($satuan), $ukuran);
                $set = $p->batasPerTransaksi !== null ? min($set, $p->batasPerTransaksi) : $set;

                for ($s = 0; $s < $set; $s++) {
                    $isi = array_slice($satuan, $s * $ukuran, $ukuran);
                    $total = array_reduce($isi, fn (Uang $t, array $u): Uang => $t->Tambah($u[1]), Uang::Nol());
                    $diskon = $total->Kurangi($p->harga ?? Uang::Nol());

                    if ($diskon->Bandingkan(Uang::Nol()) <= 0) {
                        continue;
                    }

                    $alokasi = $this->pengalokasi->AlokasikanSebanding($diskon, array_map(fn (array $u): Uang => $u[1], $isi));

                    foreach ($isi as $n => $u) {
                        $tambah($u[0], $alokasi[$n]);
                    }
                }

                break;
            case JenisAksiPromo::DiskonPersenPesanan:
            case JenisAksiPromo::DiskonTetapPesanan:
            case JenisAksiPromo::PoinBerlipat:
                break;
        }

        return $hasil;
    }

    /**
     * Terapkan `daftar` (urut prioritas): promo barang dulu lalu promo pesanan; promo tanpa potongan dibuang.
     *
     * @param  list<DefinisiPromo>  $daftar
     * @param  list<BarisPromo>  $barisPromo
     * @param  list<Uang>  $sisaAwal
     * @param  list<Uang>  $bruto
     * @return list<PromoTerpakai>
     */
    private function Evaluasi(array $daftar, DataKalkulasi $dasar, array $barisPromo, array $sisaAwal, array $bruto): array
    {
        $sisa = $sisaAwal;
        $potonganBarang = [];

        foreach ($daftar as $p) {
            if ($p->aksi->CekPesanan()) {
                continue;
            }

            $terpakai = [];
            $mentah = $this->HitungDiskonBarang($p, $dasar, $barisPromo, $bruto);
            ksort($mentah);

            foreach ($mentah as $i => $nilai) {
                $nilai = $nilai->Bandingkan($sisa[$i]) > 0 ? $sisa[$i] : $nilai;

                if ($nilai->Bandingkan(Uang::Nol()) > 0) {
                    $terpakai[$i] = $nilai;
                    $sisa[$i] = $sisa[$i]->Kurangi($nilai);
                }
            }

            $potonganBarang[$p->uuid] = $terpakai;
        }

        $sisaPesanan = array_reduce($sisa, fn (Uang $t, Uang $s): Uang => $t->Tambah($s), Uang::Nol());
        $dasarPesanan = $sisaPesanan;
        $potonganPesanan = [];

        foreach ($daftar as $p) {
            if (! $p->aksi->CekPesanan()) {
                continue;
            }

            $mentah = $p->aksi === JenisAksiPromo::DiskonPersenPesanan
                ? $dasarPesanan->Kali(($p->persen ?? BigDecimal::zero())->withPointMovedLeft(2))
                : ($p->jumlah ?? Uang::Nol());
            $nilai = $mentah->Bandingkan($sisaPesanan) > 0 ? $sisaPesanan : $mentah;
            $potonganPesanan[$p->uuid] = $nilai;
            $sisaPesanan = $sisaPesanan->Kurangi($nilai);
        }

        $hasil = [];

        foreach ($daftar as $p) {
            $pakai = $p->aksi->CekPesanan()
                ? $potonganPesanan[$p->uuid]->Bandingkan(Uang::Nol()) > 0
                : ($potonganBarang[$p->uuid] ?? []) !== [];

            if ($pakai) {
                $hasil[] = new PromoTerpakai($p->uuid, $p->kode, $potonganBarang[$p->uuid] ?? [], $potonganPesanan[$p->uuid] ?? Uang::Nol());
            }
        }

        return $hasil;
    }
}
