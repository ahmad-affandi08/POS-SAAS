<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Laporan\Enum\LaporanUnggulan;
use App\Domain\Pajak\Data\BatasBiayaLayanan;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\TemplateSektor\Enum\JenisProdukContoh;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Enum\ModeKasir;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Referensi\Model\SatuanStandar;
use App\Domain\Tenant\Model\Fitur;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * Validasi otomatis isi template sektor (P-03 langkah 3, BR-P03.3). Aturan lengkap ada di PRD P-03.
 * Isi yang bentuknya rusak tidak memicu exception, tetapi dilaporkan sebagai galat per bagian.
 */
final class ValidatorTemplate
{
    /** Kode akun §11.2: satu digit tipe, tanda hubung, empat digit. */
    public const POLA_KODE_AKUN = '/^[1-6]-\d{4}$/';

    /** Harga produk contoh: string desimal Rupiah (DECIMAL(18,2)), tanpa pemisah ribuan. Tidak pernah float. */
    public const POLA_HARGA = '/^\d{1,16}(\.\d{1,2})?$/';

    public const JUMLAH_PRODUK_CONTOH_MAKSIMAL = 100;

    /** Sama dengan panjang kolom `Produk.Nama` (§15). */
    public const PANJANG_NAMA_PRODUK_MAKSIMAL = 150;

    /** @var list<array{Bagian: string, Pesan: string}> */
    private array $galat = [];

    /**
     * @param  array<mixed>  $isi
     * @return array{Lolos: bool, Galat: list<array{Bagian: string, Pesan: string}>}
     */
    public function Validasi(array $isi): array
    {
        $this->galat = [];

        $this->ValidasiModeKasir($isi);
        $this->ValidasiFitur($this->AmbilDaftarTeks($isi, 'KunciFitur'));
        $akun = $this->ValidasiAkun($isi['Akun'] ?? null);
        $this->ValidasiPemetaanAkun($isi['PemetaanAkun'] ?? null, $akun);
        $this->ValidasiSatuan($this->AmbilDaftarTeks($isi, 'KodeSatuan'));
        $this->ValidasiKelompokPajak($isi['KelompokPajak'] ?? [], is_array($isi['Pengaturan'] ?? null) && ($isi['Pengaturan']['BiayaLayananMasukDpp'] ?? false) === true);
        $this->ValidasiPengaturan($isi['Pengaturan'] ?? null);

        foreach (['Kategori', 'StasiunDapur', 'AlasanVoid', 'AlasanPenyesuaian'] as $bagian) {
            $this->ValidasiDaftarNama($isi, $bagian);
        }

        foreach ($this->AmbilDaftarTeks($isi, 'LaporanUnggulan') as $laporan) {
            if (LaporanUnggulan::tryFrom($laporan) === null) {
                $this->Catat('LaporanUnggulan', "Laporan {$laporan} tidak dikenal.");
            }
        }

        $this->ValidasiProdukContoh($isi);

        return ['Lolos' => $this->galat === [], 'Galat' => $this->galat];
    }

    /**
     * Produk contoh untuk langkah produk awal panduan (F-01 langkah 4a, DesainF01 C3). Kunci boleh tidak ada
     * (versi lama) dan dianggap daftar kosong. Kategori dan satuan harus merujuk isi template ini sendiri agar
     * penerapan ke tenant selalu menemukan kategori/satuan yang sudah dibuat dari template yang sama.
     *
     * @param  array<mixed>  $isi
     */
    private function ValidasiProdukContoh(array $isi): void
    {
        $daftar = $isi['ProdukContoh'] ?? [];

        if (! is_array($daftar) || ! array_is_list($daftar)) {
            $this->Catat('ProdukContoh', 'Produk contoh harus berupa daftar.');

            return;
        }

        if (count($daftar) > self::JUMLAH_PRODUK_CONTOH_MAKSIMAL) {
            $this->Catat('ProdukContoh', 'Produk contoh paling banyak '.self::JUMLAH_PRODUK_CONTOH_MAKSIMAL.' item.');
        }

        $kategori = [];

        foreach (is_array($isi['Kategori'] ?? null) ? $isi['Kategori'] : [] as $nama) {
            if (is_string($nama)) {
                $kategori[mb_strtolower(trim($nama))] = true;
            }
        }

        $kodeSatuan = is_array($isi['KodeSatuan'] ?? null) ? array_filter($isi['KodeSatuan'], 'is_string') : [];
        $namaTerlihat = [];

        foreach ($daftar as $indeks => $produk) {
            $baris = 'baris '.($indeks + 1);

            if (! is_array($produk)) {
                $this->Catat('ProdukContoh', "Produk contoh {$baris} tidak berbentuk isian produk.");

                continue;
            }

            $nama = is_string($produk['Nama'] ?? null) ? trim($produk['Nama']) : '';

            if ($nama === '') {
                $this->Catat('ProdukContoh', "Nama produk contoh {$baris} wajib diisi.");
            } elseif (mb_strlen($nama) > self::PANJANG_NAMA_PRODUK_MAKSIMAL) {
                $this->Catat('ProdukContoh', "Nama produk contoh {$baris} paling panjang ".self::PANJANG_NAMA_PRODUK_MAKSIMAL.' karakter.');
            } elseif (isset($namaTerlihat[mb_strtolower($nama)])) {
                $this->Catat('ProdukContoh', "Produk contoh {$nama} ganda.");
            }

            $label = $nama === '' ? $baris : $nama;

            if ($nama !== '') {
                $namaTerlihat[mb_strtolower($nama)] = true;
            }

            $namaKategori = $produk['Kategori'] ?? null;

            if ($namaKategori !== null && (! is_string($namaKategori) || ! isset($kategori[mb_strtolower(trim($namaKategori))]))) {
                $teks = is_string($namaKategori) ? $namaKategori : '(bukan teks)';
                $this->Catat('ProdukContoh', "Produk contoh {$label}: kategori {$teks} tidak ada di daftar kategori template.");
            }

            $harga = $produk['Harga'] ?? null;

            if (! is_string($harga) || preg_match(self::POLA_HARGA, $harga) !== 1) {
                $this->Catat('ProdukContoh', "Produk contoh {$label}: harga harus angka Rupiah tanpa titik ribuan, paling banyak 2 desimal (misal 22000).");
            }

            $satuan = $produk['KodeSatuan'] ?? null;

            if (! is_string($satuan) || ! in_array($satuan, $kodeSatuan, true)) {
                $teks = is_string($satuan) && $satuan !== '' ? $satuan : '(kosong)';
                $this->Catat('ProdukContoh', "Produk contoh {$label}: satuan {$teks} tidak ada di daftar satuan template.");
            }

            $jenis = $produk['Jenis'] ?? null;

            if (! is_string($jenis) || JenisProdukContoh::tryFrom($jenis) === null) {
                $teks = is_string($jenis) && $jenis !== '' ? $jenis : '(kosong)';
                $pilihan = implode(', ', array_map(fn (JenisProdukContoh $jenisContoh) => $jenisContoh->value, JenisProdukContoh::cases()));
                $this->Catat('ProdukContoh', "Produk contoh {$label}: jenis {$teks} tidak dikenal. Pilih salah satu: {$pilihan}.");
            }
        }
    }

    /**
     * @param  array<mixed>  $isi
     */
    private function ValidasiModeKasir(array $isi): void
    {
        $daftar = $this->AmbilDaftarTeks($isi, 'ModeKasir');

        if ($daftar === []) {
            $this->Catat('ModeKasir', 'Pilih minimal satu mode kasir.');
        }

        foreach ($daftar as $mode) {
            if (ModeKasir::tryFrom($mode) === null) {
                $this->Catat('ModeKasir', "Mode kasir {$mode} tidak dikenal.");
            }
        }

        $default = $isi['ModeKasirDefault'] ?? null;

        if (! is_string($default) || ! in_array($default, $daftar, true)) {
            $this->Catat('ModeKasir', 'Mode kasir default harus salah satu mode yang dipilih.');
        }

        $this->CatatGanda('ModeKasir', $daftar, 'Mode kasir');
    }

    /**
     * @param  list<string>  $daftar
     */
    private function ValidasiFitur(array $daftar): void
    {
        $this->CatatGanda('KunciFitur', $daftar, 'Fitur');
        $ada = $daftar === [] ? [] : Fitur::query()->whereIn('Kunci', $daftar)->pluck('Kunci')->all();

        foreach (array_diff($daftar, $ada) as $kunci) {
            $this->Catat('KunciFitur', "Fitur {$kunci} tidak ada di katalog fitur (P-04).");
        }
    }

    /**
     * @return array<string, array{Tipe: TipeAkun, Kontra: bool}> Akun valid per kode.
     */
    private function ValidasiAkun(mixed $daftar): array
    {
        if (! is_array($daftar) || $daftar === []) {
            $this->Catat('Akun', 'COA wajib berisi minimal satu akun.');

            return [];
        }

        $akunValid = [];
        $kodeTerlihat = [];

        foreach ($daftar as $akun) {
            $kode = is_array($akun) && is_string($akun['Kode'] ?? null) ? $akun['Kode'] : null;

            if ($kode === null || preg_match(self::POLA_KODE_AKUN, $kode) !== 1) {
                $this->Catat('Akun', 'Kode akun '.($kode ?? '(kosong)').' harus berformat 1-1100 (digit tipe, tanda hubung, empat digit).');

                continue;
            }

            if (isset($kodeTerlihat[$kode])) {
                $this->Catat('Akun', "Kode akun {$kode} ganda.");

                continue;
            }

            $kodeTerlihat[$kode] = true;

            if (! is_string($akun['Nama'] ?? null) || trim($akun['Nama']) === '') {
                $this->Catat('Akun', "Nama akun {$kode} wajib diisi.");
            }

            $tipe = is_string($akun['Tipe'] ?? null) ? TipeAkun::tryFrom($akun['Tipe']) : null;

            if ($tipe === null) {
                $this->Catat('Akun', "Tipe akun {$kode} tidak dikenal.");

                continue;
            }

            if (! str_starts_with($kode, $tipe->AmbilDigitAwalKode().'-')) {
                $this->Catat('Akun', "Akun {$kode} bertipe {$tipe->AmbilLabel()} harus berkode awal {$tipe->AmbilDigitAwalKode()}-.");
            }

            $kontra = ($akun['Kontra'] ?? false) === true;
            $saldoNormal = is_string($akun['SaldoNormal'] ?? null) ? SaldoNormal::tryFrom($akun['SaldoNormal']) : null;
            $seharusnya = $tipe->AmbilSaldoNormal($kontra);

            if ($saldoNormal !== $seharusnya) {
                $this->Catat('Akun', "Saldo normal akun {$kode} seharusnya {$seharusnya->value}".($kontra ? ' (akun kontra).' : '.'));
            }

            $akunValid[$kode] = ['Tipe' => $tipe, 'Kontra' => $kontra];
        }

        return $akunValid;
    }

    /**
     * @param  array<string, array{Tipe: TipeAkun, Kontra: bool}>  $akun
     */
    private function ValidasiPemetaanAkun(mixed $pemetaan, array $akun): void
    {
        $pemetaan = is_array($pemetaan) ? $pemetaan : [];
        $kunciPerPeran = [];

        foreach (array_keys($pemetaan) as $kunci) {
            $peran = PeranAkun::DariKunci((string) $kunci);

            if ($peran === null) {
                $this->Catat('PemetaanAkun', "Peran akun {$kunci} tidak dikenal.");

                continue;
            }

            $kunciPerPeran[$peran->value][] = (string) $kunci;
        }

        foreach ($kunciPerPeran as $nilaiPeran => $daftarKunci) {
            if (count($daftarKunci) > 1) {
                $this->Catat('PemetaanAkun', 'Peran "'.PeranAkun::from($nilaiPeran)->AmbilLabel().'" dipetakan dua kali (kunci '.implode(' dan ', $daftarKunci).'). Hapus salah satunya.');
            }
        }

        // Versi terbit lama masih memakai kunci lama (BR-P03.4 melarang menulis ulang), jadi dibaca lewat alias.
        $pemetaan = PeranAkun::NormalisasiPemetaan($pemetaan);

        foreach (PeranAkun::cases() as $peran) {
            $kode = $pemetaan[$peran->value] ?? null;

            if (! is_string($kode) || $kode === '') {
                $this->Catat('PemetaanAkun', "Peran \"{$peran->AmbilLabel()}\" belum dipetakan ke akun.");

                continue;
            }

            $dataAkun = $akun[$kode] ?? null;

            if ($dataAkun === null) {
                $this->Catat('PemetaanAkun', "Peran \"{$peran->AmbilLabel()}\" merujuk akun {$kode} yang tidak ada di COA.");

                continue;
            }

            // Aturan tipe & kontra per peran satu sumber dengan pemetaan akun tenant (F-13a).
            foreach ($peran->PeriksaAkun($dataAkun['Tipe'], $dataAkun['Kontra'], $kode) as $pesan) {
                $this->Catat('PemetaanAkun', $pesan);
            }
        }
    }

    /**
     * @param  list<string>  $daftar
     */
    private function ValidasiSatuan(array $daftar): void
    {
        if ($daftar === []) {
            $this->Catat('KodeSatuan', 'Pilih minimal satu satuan.');

            return;
        }

        $this->CatatGanda('KodeSatuan', $daftar, 'Satuan');
        $ada = SatuanStandar::query()->whereIn('Kode', $daftar)->where('Aktif', true)->pluck('Kode')->all();

        foreach (array_diff($daftar, $ada) as $kode) {
            $this->Catat('KodeSatuan', "Satuan {$kode} tidak ada atau tidak aktif di satuan standar (P-02).");
        }
    }

    private function ValidasiKelompokPajak(mixed $daftar, bool $biayaLayananMasukDpp): void
    {
        if (! is_array($daftar)) {
            $this->Catat('KelompokPajak', 'Kelompok pajak harus berupa daftar (boleh kosong).');

            return;
        }

        $kodeJenis = [];

        foreach ($daftar as $kelompok) {
            foreach (is_array($kelompok) && is_array($kelompok['Detail'] ?? null) ? $kelompok['Detail'] : [] as $detail) {
                if (is_array($detail) && is_string($detail['KodeJenisPajak'] ?? null)) {
                    $kodeJenis[] = $detail['KodeJenisPajak'];
                }
            }
        }

        $jenisPajak = $kodeJenis === [] ? collect() : JenisPajak::query()->whereIn('Kode', array_unique($kodeJenis))->get()->keyBy('Kode');
        $idNasional = $jenisPajak->filter(fn (JenisPajak $jenis) => $jenis->Cakupan === CakupanPajak::Nasional)->pluck('Id')->all();
        $idPunyaTarifTerbit = $idNasional === [] ? [] : TarifPajak::query()
            ->whereIn('IdJenisPajak', $idNasional)
            ->where('Status', StatusDataMaster::Terbit->value)
            ->where(fn ($kueri) => $kueri->whereNull('BerlakuSampai')->orWhereDate('BerlakuSampai', '>=', now('Asia/Jakarta')->toDateString()))
            ->distinct()
            ->pluck('IdJenisPajak')
            ->all();

        $namaTerlihat = [];

        foreach ($daftar as $kelompok) {
            $nama = is_array($kelompok) && is_string($kelompok['Nama'] ?? null) ? trim($kelompok['Nama']) : '';

            if ($nama === '') {
                $this->Catat('KelompokPajak', 'Nama kelompok pajak wajib diisi.');

                continue;
            }

            if (isset($namaTerlihat[mb_strtolower($nama)])) {
                $this->Catat('KelompokPajak', "Kelompok pajak {$nama} ganda.");
            }

            $namaTerlihat[mb_strtolower($nama)] = true;
            $detail = is_array($kelompok['Detail'] ?? null) ? $kelompok['Detail'] : [];

            if ($detail === []) {
                $this->Catat('KelompokPajak', "Kelompok pajak {$nama} belum berisi pajak.");
            }

            $jenisTerlihat = [];
            $urutanTerlihat = [];

            foreach ($detail as $baris) {
                $kode = is_array($baris) && is_string($baris['KodeJenisPajak'] ?? null) ? $baris['KodeJenisPajak'] : '';
                $jenis = $jenisPajak->get($kode);

                if (! $jenis instanceof JenisPajak) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: jenis pajak {$kode} tidak ada di data regulasi (P-02).");

                    continue;
                }

                if (isset($jenisTerlihat[$kode])) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: jenis pajak {$kode} ganda.");
                }

                $jenisTerlihat[$kode] = true;

                if ($jenis->Cakupan === CakupanPajak::Nasional && ! in_array($jenis->Id, $idPunyaTarifTerbit, true)) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: {$jenis->Nama} belum punya tarif terbit yang masih berlaku.");
                }

                $dasar = is_string($baris['DasarPengenaan'] ?? null) ? DasarPengenaanPajak::tryFrom($baris['DasarPengenaan']) : null;

                if ($dasar === null) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: dasar pengenaan {$kode} tidak dikenal.");
                } elseif ($dasar === DasarPengenaanPajak::SubtotalPlusLayanan && ! $biayaLayananMasukDpp) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: {$kode} memakai subtotal + biaya layanan, padahal pengaturan biaya layanan tidak masuk DPP.");
                }

                $urutan = $baris['Urutan'] ?? null;

                if (! is_int($urutan) || $urutan < 1 || $urutan > 9 || isset($urutanTerlihat[$urutan])) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: urutan {$kode} harus angka 1–9 dan tidak ganda.");
                }

                if (is_int($urutan)) {
                    $urutanTerlihat[$urutan] = true;
                }
            }
        }
    }

    private function ValidasiPengaturan(mixed $pengaturan): void
    {
        if (! is_array($pengaturan)) {
            $this->Catat('Pengaturan', 'Pengaturan default wajib diisi.');

            return;
        }

        $pembulatan = is_array($pengaturan['PembulatanTunai'] ?? null) ? $pengaturan['PembulatanTunai'] : [];
        $kelipatan = $pembulatan['Kelipatan'] ?? null;

        if (! is_int($kelipatan) || $kelipatan < 1 || $kelipatan > 1000) {
            $this->Catat('Pengaturan', 'Kelipatan pembulatan tunai harus bilangan bulat Rupiah 1 sampai 1.000 (misal 100).');
        }

        if (! is_string($pembulatan['Arah'] ?? null) || ArahPembulatan::tryFrom($pembulatan['Arah']) === null) {
            $this->Catat('Pengaturan', 'Arah pembulatan tunai tidak dikenal.');
        }

        $persen = self::AmbilDesimal($pengaturan['PersenBiayaLayanan'] ?? null);

        if ($persen === null || $persen->isNegative() || $persen->isGreaterThan(BatasBiayaLayanan::PERSEN_MAKSIMAL)) {
            $this->Catat('Pengaturan', 'Biaya layanan harus 0 sampai '.BatasBiayaLayanan::PERSEN_MAKSIMAL.' persen.');
        }

        if (! is_string($pengaturan['MetodeHpp'] ?? null) || MetodeHpp::tryFrom($pengaturan['MetodeHpp']) === null) {
            $this->Catat('Pengaturan', 'Metode HPP tidak dikenal.');
        }

        foreach (['BiayaLayananMasukDpp', 'StokBolehMinus', 'HargaTermasukPajak'] as $kunci) {
            if (! is_bool($pengaturan[$kunci] ?? null)) {
                $this->Catat('Pengaturan', "Pengaturan {$kunci} wajib ya atau tidak.");
            }
        }
    }

    /**
     * @param  array<mixed>  $isi
     */
    private function ValidasiDaftarNama(array $isi, string $bagian): void
    {
        $daftar = $this->AmbilDaftarTeks($isi, $bagian);

        foreach ($daftar as $nama) {
            if (trim($nama) === '') {
                $this->Catat($bagian, 'Nama tidak boleh kosong.');
            }
        }

        $this->CatatGanda($bagian, array_map(fn (string $nama) => mb_strtolower(trim($nama)), $daftar), 'Nama');
    }

    /**
     * @param  list<string>  $daftar
     */
    private function CatatGanda(string $bagian, array $daftar, string $label): void
    {
        foreach (array_keys(array_filter(array_count_values($daftar), fn (int $jumlah) => $jumlah > 1)) as $nilai) {
            $this->Catat($bagian, "{$label} {$nilai} ganda.");
        }
    }

    private function Catat(string $bagian, string $pesan): void
    {
        $this->galat[] = ['Bagian' => $bagian, 'Pesan' => $pesan];
    }

    /**
     * Daftar teks pada isi. Elemen yang bukan teks dilaporkan, bukan dibuang diam-diam.
     *
     * @param  array<mixed>  $isi
     * @return list<string>
     */
    private function AmbilDaftarTeks(array $isi, string $kunci): array
    {
        $daftar = $isi[$kunci] ?? [];

        if (! is_array($daftar) || ! array_is_list($daftar) || count(array_filter($daftar, 'is_string')) !== count($daftar)) {
            $this->Catat($kunci, 'Isian harus berupa daftar teks.');

            return is_array($daftar) ? array_values(array_filter($daftar, 'is_string')) : [];
        }

        return $daftar;
    }

    private static function AmbilDesimal(mixed $nilai): ?BigDecimal
    {
        if (! is_string($nilai) && ! is_int($nilai)) {
            return null;
        }

        try {
            return BigDecimal::of($nilai);
        } catch (MathException) {
            return null;
        }
    }
}
