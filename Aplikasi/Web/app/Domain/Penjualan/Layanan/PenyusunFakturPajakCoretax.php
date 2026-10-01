<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Kueri\KodeCoretaxProduk;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\Pelanggan\Kueri\IdentitasPajakPelanggan;
use App\Domain\Penjualan\Data\DataBarisFakturPajak;
use App\Domain\Penjualan\Data\DataFakturPajak;
use App\Domain\Penjualan\Data\HasilFakturPajakCoretax;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPotongan;
use App\Domain\Penjualan\Kalkulasi\MesinKalkulasi;
use App\Domain\Penjualan\Model\FakturPenjualan;
use App\Domain\Penjualan\Model\SuratJalan;
use App\Domain\Penjualan\Model\SuratJalanDetail;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Menyusun data Faktur Pajak Keluaran untuk impor massal Coretax dari faktur penjualan grosir (PRD v3.12, F-12).
 *
 * **Sumbernya faktur grosir, bukan penjualan kasir.** Faktur Pajak hanya diterbitkan untuk penyerahan kepada pembeli
 * ber-identitas pajak; kasir ritel menerbitkan struk (PMK tentang dokumen tertentu yang kedudukannya sama dengan Faktur
 * Pajak) dan tidak diekspor di sini.
 *
 * **Angka dihitung ulang dengan mesin kalkulasi yang sama dengan dokumen** (`MesinKalkulasi` + snapshot tarif & pengali
 * DPP dari surat jalan) sehingga DPP baris identik dengan DPP dokumen. Coretax menghitung `OtherTaxBase` dan `VAT` per
 * baris dengan pembulatan sendiri, jadi ΣPPN Coretax bisa berbeda beberapa sen dari PPN faktur internal; selisihnya
 * **dilaporkan** (`selisihDpp`/`selisihPpn`), tidak disembunyikan dan tidak diubah di pembukuan.
 *
 * Faktur yang tidak bisa diekspor dengan benar (pembeli tanpa NPWP/NIK, baris non-PPN, pengali DPP selain 11/12,
 * tanpa PPN) **dilewati dan dijelaskan** — lebih baik pengguna melihat daftar pekerjaan rumah daripada Coretax menolak
 * seluruh berkas karena satu baris.
 */
final class PenyusunFakturPajakCoretax
{
    private const KODE_BARANG_BAWAAN = '000000';

    private const KODE_SATUAN_BAWAAN = 'UM.0021';

    private const SUFIKS_TKU = '000000';

    private const TIN_NIK = '0000000000000000';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profilTenant,
        private readonly ProfilPajakOutlet $profilOutlet,
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly IdentitasPajakPelanggan $identitasPelanggan,
        private readonly KodeCoretaxProduk $kodeProduk,
        private readonly MesinKalkulasi $mesin = new MesinKalkulasi,
    ) {}

    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet
     */
    public function Susun(CarbonImmutable $dari, CarbonImmutable $sampai, ?array $idOutlet): HasilFakturPajakCoretax
    {
        $profil = $this->profilTenant->Ambil($this->konteks->Wajib());
        $masalahUmum = [];
        $tin = self::NormalisasiNpwp($profil['Npwp'] ?? null);

        if (! $profil['Pkp']) {
            $masalahUmum[] = 'Usaha Anda belum ditandai sebagai PKP. Faktur Pajak hanya diterbitkan oleh Pengusaha Kena Pajak.';
        }

        if ($tin === null) {
            $masalahUmum[] = 'NPWP usaha belum diisi atau tidak sah (harus 15 atau 16 digit). Isi di pengaturan usaha.';
        }

        $query = FakturPenjualan::query()
            ->where('Status', StatusDokumenTerposting::Diposting->value)
            ->whereBetween('Tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('Tanggal')->orderBy('Id');

        if ($idOutlet !== null) {
            $query->whereIn('IdOutlet', $idOutlet);
        }

        $daftarFaktur = $query->get();
        $suratJalanPerFaktur = $this->AmbilSuratJalan(array_values(array_map('intval', $daftarFaktur->pluck('Id')->all())));
        $pembeli = $this->identitasPelanggan->AmbilBanyak(array_values(array_unique($daftarFaktur->pluck('IdPelanggan')->all())));
        $semuaDetail = [];

        foreach ($suratJalanPerFaktur as $daftar) {
            foreach ($daftar as $sj) {
                foreach ($sj['Detail'] as $d) {
                    $semuaDetail[] = $d;
                }
            }
        }

        $kode = $this->kodeProduk->AmbilBanyak(array_values(array_unique(array_map(fn (SuratJalanDetail $d): int => $d->IdProduk, $semuaDetail))));
        $pajakKelompok = $this->kelompokPajak->AmbilJenisPajakPerKelompok(array_values(array_unique(array_filter(
            array_map(fn (SuratJalanDetail $d): ?int => $d->IdKelompokPajak, $semuaDetail),
            'is_int',
        ))));

        $hasilFaktur = [];
        $masalahFaktur = [];
        $peringatan = [];

        foreach ($daftarFaktur as $faktur) {
            $masalah = [];
            $susunan = $this->SusunFaktur(
                $faktur,
                $suratJalanPerFaktur[$faktur->Id] ?? [],
                $pembeli[$faktur->IdPelanggan] ?? null,
                $kode,
                $pajakKelompok,
                $masalah,
                $peringatan,
            );

            if ($masalah !== []) {
                $masalahFaktur[$faktur->Nomor] = $masalah;

                continue;
            }

            if ($susunan !== null) {
                $hasilFaktur[] = $susunan;
            }
        }

        return new HasilFakturPajakCoretax(
            tinPenjual: $tin ?? '',
            faktur: $hasilFaktur,
            masalahUmum: $masalahUmum,
            masalahFaktur: $masalahFaktur,
            peringatan: array_values(array_unique($peringatan)),
            jumlahDiperiksa: $daftarFaktur->count(),
        );
    }

    /**
     * NPWP 15 digit diberi awalan "0" menjadi 16 digit (format NPWP baru); 16 digit dipakai apa adanya; selain itu tidak sah.
     */
    public static function NormalisasiNpwp(?string $npwp): ?string
    {
        $digit = preg_replace('/\D/', '', (string) $npwp) ?? '';

        return match (strlen($digit)) {
            15 => '0'.$digit,
            16 => $digit,
            default => null,
        };
    }

    /**
     * @param  list<int>  $idFaktur
     * @return array<int, list<array{SuratJalan: SuratJalan, Detail: list<SuratJalanDetail>}>>
     */
    private function AmbilSuratJalan(array $idFaktur): array
    {
        if ($idFaktur === []) {
            return [];
        }

        $suratJalan = SuratJalan::query()
            ->whereIn('IdFakturPenjualan', $idFaktur)
            ->where('Status', StatusDokumenTerposting::Diposting->value)
            ->orderBy('Tanggal')->orderBy('Id')->get();
        $detail = SuratJalanDetail::query()->whereIn('IdSuratJalan', $suratJalan->pluck('Id')->all())->orderBy('IdSuratJalan')->orderBy('Urutan')->get()->groupBy('IdSuratJalan');
        $hasil = [];

        foreach ($suratJalan as $sj) {
            $hasil[(int) $sj->IdFakturPenjualan][] = ['SuratJalan' => $sj, 'Detail' => array_values(($detail->get($sj->Id) ?? collect())->all())];
        }

        return $hasil;
    }

    /**
     * @param  list<array{SuratJalan: SuratJalan, Detail: list<SuratJalanDetail>}>  $suratJalan
     * @param  array{Nama: string, Alamat: string|null, Email: string|null, Npwp: string|null, Nik: string|null, NamaNpwp: string|null, AlamatNpwp: string|null}|null  $pembeli
     * @param  array<int, array{Kode: string|null, Unit: string|null, Jasa: bool}>  $kode
     * @param  array<int, list<array{Kode: string, Kategori: KategoriJenisPajak, Nama: string, DasarPengenaan: mixed, KenaBiayaKirim: bool}>>  $pajakKelompok
     * @param  list<string>  $masalah
     * @param  list<string>  $peringatan
     */
    private function SusunFaktur(
        FakturPenjualan $faktur,
        array $suratJalan,
        ?array $pembeli,
        array $kode,
        array $pajakKelompok,
        array &$masalah,
        array &$peringatan,
    ): ?DataFakturPajak {
        if ($faktur->TarifPpn === null) {
            $masalah[] = 'Faktur ini tidak memuat PPN (tidak ada tarif PPN tersimpan), jadi tidak diekspor sebagai Faktur Pajak.';
        } elseif ($faktur->PengaliDppPembilang !== 11 || $faktur->PengaliDppPenyebut !== 12) {
            $masalah[] = "Faktur ini memakai pengali DPP {$faktur->PengaliDppPembilang}/{$faktur->PengaliDppPenyebut}; ekspor baru mendukung DPP nilai lain 11/12 (kode transaksi 04). Terbitkan Faktur Pajaknya langsung di Coretax.";
        }

        $identitas = $this->SusunIdentitasPembeli($pembeli, $masalah);

        if ($suratJalan === []) {
            $masalah[] = 'Faktur ini tidak memiliki surat jalan aktif.';
        }

        if ($masalah !== []) {
            return null;
        }

        $baris = [];
        $totalDpp = Uang::Nol();
        $totalPpn = Uang::Nol();
        $tarif = BigDecimal::of((string) $faktur->TarifPpn);

        foreach ($suratJalan as $isi) {
            $hasil = $this->HitungSuratJalan($isi['SuratJalan'], $isi['Detail'], $pajakKelompok, $tarif);

            foreach ($isi['Detail'] as $indeks => $detail) {
                $baris[] = $this->SusunBaris($detail, $hasil[$indeks] ?? null, $tarif, $kode[$detail->IdProduk] ?? null, $masalah, $peringatan);
            }
        }

        if ($masalah !== []) {
            $masalah = array_values(array_unique($masalah));

            return null;
        }

        foreach ($baris as $b) {
            $totalDpp = $totalDpp->Tambah(Uang::Dari($b->dpp));
            $totalPpn = $totalPpn->Tambah(Uang::Dari($b->ppn));
        }

        return new DataFakturPajak(
            nomorFaktur: $faktur->Nomor,
            tanggal: $faktur->Tanggal->format('Y-m-d'),
            namaPembeli: $identitas['Nama'],
            alamatPembeli: $identitas['Alamat'],
            emailPembeli: $identitas['Email'],
            jenisDokumenPembeli: $identitas['JenisDokumen'],
            tinPembeli: $identitas['Tin'],
            nomorDokumenPembeli: $identitas['NomorDokumen'],
            idTkuPembeli: $identitas['IdTku'],
            baris: $baris,
            totalDpp: $totalDpp->KeString(),
            totalPpn: $totalPpn->KeString(),
            selisihDpp: $totalDpp->Kurangi(Uang::Dari($faktur->DasarPengenaanPajak))->KeString(),
            selisihPpn: $totalPpn->Kurangi(Uang::Dari($faktur->Pajak))->KeString(),
        );
    }

    /**
     * @param  array{Nama: string, Alamat: string|null, Email: string|null, Npwp: string|null, Nik: string|null, NamaNpwp: string|null, AlamatNpwp: string|null}|null  $pembeli
     * @param  list<string>  $masalah
     * @return array{Nama: string, Alamat: string, Email: string|null, JenisDokumen: string, Tin: string, NomorDokumen: string, IdTku: string}
     */
    private function SusunIdentitasPembeli(?array $pembeli, array &$masalah): array
    {
        $kosong = ['Nama' => '', 'Alamat' => '', 'Email' => null, 'JenisDokumen' => 'TIN', 'Tin' => '', 'NomorDokumen' => '', 'IdTku' => ''];

        if ($pembeli === null) {
            $masalah[] = 'Pelanggan faktur ini tidak ditemukan.';

            return $kosong;
        }

        $nama = trim((string) ($pembeli['NamaNpwp'] ?? '')) !== '' ? (string) $pembeli['NamaNpwp'] : $pembeli['Nama'];
        $alamat = trim((string) ($pembeli['AlamatNpwp'] ?? '')) !== '' ? (string) $pembeli['AlamatNpwp'] : (string) ($pembeli['Alamat'] ?? '');
        $tin = self::NormalisasiNpwp($pembeli['Npwp']);
        $nik = preg_replace('/\D/', '', (string) $pembeli['Nik']) ?? '';

        if ($tin !== null) {
            return ['Nama' => $nama, 'Alamat' => $alamat, 'Email' => $pembeli['Email'], 'JenisDokumen' => 'TIN', 'Tin' => $tin, 'NomorDokumen' => '', 'IdTku' => $tin.self::SUFIKS_TKU];
        }

        if (strlen($nik) === 16) {
            return ['Nama' => $nama, 'Alamat' => $alamat, 'Email' => $pembeli['Email'], 'JenisDokumen' => 'National ID', 'Tin' => self::TIN_NIK, 'NomorDokumen' => $nik, 'IdTku' => self::SUFIKS_TKU];
        }

        $masalah[] = "Pelanggan {$pembeli['Nama']} belum punya NPWP atau NIK yang sah. Lengkapi di data pelanggan (Identitas pajak).";

        return $kosong;
    }

    /**
     * Hasil mesin kalkulasi per baris surat jalan (indeks sama dengan `$detail`); null untuk baris yang bukan objek PPN.
     *
     * @param  list<SuratJalanDetail>  $detail
     * @param  array<int, list<array{Kode: string, Kategori: KategoriJenisPajak, Nama: string, DasarPengenaan: mixed, KenaBiayaKirim: bool}>>  $pajakKelompok
     * @return array<int, array{Bruto: Uang, Diskon: Uang, Dpp: Uang}|null>
     */
    private function HitungSuratJalan(SuratJalan $sj, array $detail, array $pajakKelompok, BigDecimal $tarif): array
    {
        $hargaTermasukPajak = ($this->profilOutlet->Ambil($sj->IdOutlet)?->hargaTermasukPajak) === true;
        $adaPpn = [];
        $baris = [];

        foreach ($detail as $indeks => $d) {
            $kenaPpn = $d->IdKelompokPajak !== null && $this->MemuatPpn($pajakKelompok[$d->IdKelompokPajak] ?? []);
            $adaPpn[$indeks] = $kenaPpn;
            $baris[] = new DataBarisKalkulasi(
                Kuantitas::Dari($d->Jumlah),
                Uang::Dari($d->Harga),
                null,
                $d->HargaTermasukPajak,
                $kenaPpn ? ['PPN'] : [],
                $d->AmbilDiskon()->BernilaiNol() ? [] : [DataPotongan::BuatNominal($d->AmbilDiskon())],
            );
        }

        $hasil = $this->mesin->Hitung(new DataKalkulasi(
            hargaTermasukPajak: $hargaTermasukPajak,
            baris: $baris,
            pajak: [new DataPajakKalkulasi('PPN', $tarif, pengaliDppPembilang: (int) $sj->PengaliDppPembilang, pengaliDppPenyebut: (int) $sj->PengaliDppPenyebut)],
        ));
        $keluar = [];

        foreach ($hasil->baris as $indeks => $b) {
            $keluar[$indeks] = ! $adaPpn[$indeks] ? null : [
                'Bruto' => $b->bruto,
                'Diskon' => $b->diskon,
                'Dpp' => $b->bruto->Kurangi($b->diskon)->Kurangi($b->pajak->Kurangi($b->pajakEksklusif)),
            ];
        }

        return $keluar;
    }

    /**
     * @param  list<array{Kode: string, Kategori: KategoriJenisPajak, Nama: string, DasarPengenaan: mixed, KenaBiayaKirim: bool}>  $jenis
     */
    private function MemuatPpn(array $jenis): bool
    {
        foreach ($jenis as $j) {
            if ($j['Kategori'] === KategoriJenisPajak::Ppn) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{Bruto: Uang, Diskon: Uang, Dpp: Uang}|null  $hitung
     * @param  array{Kode: string|null, Unit: string|null, Jasa: bool}|null  $kode
     * @param  list<string>  $masalah
     * @param  list<string>  $peringatan
     */
    private function SusunBaris(SuratJalanDetail $d, ?array $hitung, BigDecimal $tarif, ?array $kode, array &$masalah, array &$peringatan): DataBarisFakturPajak
    {
        if ($hitung === null) {
            $masalah[] = "Baris \"{$d->NamaProduk}\" bukan objek PPN (kelompok pajaknya tidak memuat PPN). Coretax tidak menerima baris tanpa PPN dalam satu faktur; pisahkan penyerahannya.";

            return new DataBarisFakturPajak('A', self::KODE_BARANG_BAWAAN, $d->NamaProduk, self::KODE_SATUAN_BAWAAN, '0', '0', '0', '0', '0', '0', '0');
        }

        $jumlah = BigDecimal::of($d->Jumlah);
        $dpp = $hitung['Dpp']->KeString();
        $dppBersih = BigDecimal::of($dpp);
        $bruto = BigDecimal::of($hitung['Bruto']->KeString());
        $diskon = BigDecimal::of($hitung['Diskon']->KeString());
        $netto = $bruto->minus($diskon);
        // Harga inklusif: bruto & diskon memuat PPN, sedangkan Coretax memakai harga dan diskon tanpa PPN. Diskon
        // diskalakan sama dengan dasar pajaknya (DPP ÷ netto) supaya rasio diskon terhadap harga tetap.
        $diskonTanpaPajak = $netto->isZero() ? $diskon : $diskon->multipliedBy($dppBersih)->dividedBy($netto, 12, RoundingMode::HalfUp);
        $hargaKotor = $dppBersih->plus($diskonTanpaPajak);
        $harga = $jumlah->isZero() ? BigDecimal::zero() : $hargaKotor->dividedBy($jumlah, 2, RoundingMode::Up);
        $totalDiskon = $harga->multipliedBy($jumlah)->minus($dppBersih)->toScale(2, RoundingMode::HalfUp);

        if ($totalDiskon->isNegative()) {
            $totalDiskon = BigDecimal::zero()->toScale(2);
        }

        $dppNilaiLain = $dppBersih->multipliedBy(11)->dividedBy(12, 2, RoundingMode::HalfUp);
        $ppn = $dppNilaiLain->multipliedBy($tarif)->dividedBy(100, 2, RoundingMode::HalfUp);
        $kodeBarang = $kode['Kode'] ?? null;
        $satuan = $kode['Unit'] ?? null;

        if ($kodeBarang === null) {
            $peringatan[] = 'Ada produk tanpa Kode Barang/Jasa Coretax: dipakai kode umum 000000. Isi di formulir produk (tab Pajak) supaya klasifikasinya benar.';
        }

        if ($satuan === null) {
            $peringatan[] = 'Ada produk tanpa Kode Satuan Coretax: dipakai UM.0021 (Unit). Isi di formulir produk (tab Pajak) bila satuannya lain.';
        }

        return new DataBarisFakturPajak(
            opsi: ($kode['Jasa'] ?? false) ? 'B' : 'A',
            kode: $kodeBarang ?? self::KODE_BARANG_BAWAAN,
            nama: $d->NamaProduk,
            satuan: $satuan ?? self::KODE_SATUAN_BAWAAN,
            harga: $harga->toScale(2)->__toString(),
            jumlah: self::RingkasAngka($jumlah),
            totalDiskon: $totalDiskon->__toString(),
            dpp: $dpp,
            dppNilaiLain: $dppNilaiLain->__toString(),
            tarifPpn: self::RingkasAngka($tarif),
            ppn: $ppn->__toString(),
        );
    }

    /**
     * Angka tanpa nol di belakang koma (`200.0000` → `200`, `12.500000` → `12.5`), tidak pernah bernotasi ilmiah.
     * Memakai `strippedOfTrailingZeros()` (brick/math 1.x; `stripTrailingZeros()` hanya ada di 0.x dan di sini menjadi Error).
     */
    public static function RingkasAngka(BigDecimal $angka): string
    {
        $ringkas = $angka->strippedOfTrailingZeros();

        return (string) ($ringkas->getScale() < 0 ? $ringkas->toScale(0) : $ringkas);
    }
}
