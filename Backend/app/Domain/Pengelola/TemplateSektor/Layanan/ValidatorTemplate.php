<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Laporan\Enum\LaporanUnggulan;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
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

    public const PERSEN_BIAYA_LAYANAN_MAKSIMAL = '10';

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
        $this->ValidasiFitur(self::AmbilDaftarTeks($isi, 'KunciFitur'));
        $akun = $this->ValidasiAkun($isi['Akun'] ?? null);
        $this->ValidasiPemetaanAkun($isi['PemetaanAkun'] ?? null, $akun);
        $this->ValidasiSatuan(self::AmbilDaftarTeks($isi, 'KodeSatuan'));
        $this->ValidasiKelompokPajak($isi['KelompokPajak'] ?? null);
        $this->ValidasiPengaturan($isi['Pengaturan'] ?? null);

        foreach (['Kategori', 'StasiunDapur', 'AlasanVoid', 'AlasanPenyesuaian'] as $bagian) {
            $this->ValidasiDaftarNama($isi, $bagian);
        }

        foreach (self::AmbilDaftarTeks($isi, 'LaporanUnggulan') as $laporan) {
            if (LaporanUnggulan::tryFrom($laporan) === null) {
                $this->Catat('LaporanUnggulan', "Laporan {$laporan} tidak dikenal.");
            }
        }

        return ['Lolos' => $this->galat === [], 'Galat' => $this->galat];
    }

    /**
     * @param  array<mixed>  $isi
     */
    private function ValidasiModeKasir(array $isi): void
    {
        $daftar = self::AmbilDaftarTeks($isi, 'ModeKasir');

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

        foreach (array_keys($pemetaan) as $peran) {
            if (PeranAkun::tryFrom((string) $peran) === null) {
                $this->Catat('PemetaanAkun', "Peran akun {$peran} tidak dikenal.");
            }
        }

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

            $tipe = $dataAkun['Tipe'];

            if ($tipe !== $peran->AmbilTipeAkun()) {
                $this->Catat('PemetaanAkun', "Peran \"{$peran->AmbilLabel()}\" harus memakai akun {$peran->AmbilTipeAkun()->AmbilLabel()}, bukan {$tipe->AmbilLabel()} ({$kode}).");
            }

            if ($peran->CekWajibKontra() !== $dataAkun['Kontra']) {
                $this->Catat('PemetaanAkun', $peran->CekWajibKontra()
                    ? "Peran \"{$peran->AmbilLabel()}\" harus memakai akun kontra ({$kode} bukan akun kontra)."
                    : "Peran \"{$peran->AmbilLabel()}\" tidak boleh memakai akun kontra ({$kode}).");
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

    private function ValidasiKelompokPajak(mixed $daftar): void
    {
        if (! is_array($daftar)) {
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
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: {$jenis->Nama} belum punya tarif terbit di P-02.");
                }

                if (! is_string($baris['DasarPengenaan'] ?? null) || DasarPengenaanPajak::tryFrom($baris['DasarPengenaan']) === null) {
                    $this->Catat('KelompokPajak', "Kelompok {$nama}: dasar pengenaan {$kode} tidak dikenal.");
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

        $kelipatan = self::AmbilDesimal($pengaturan['KelipatanPembulatan'] ?? null);

        if ($kelipatan === null || ! $kelipatan->isPositive() || ! $kelipatan->getFractionalPart()->isZero()) {
            $this->Catat('Pengaturan', 'Kelipatan pembulatan harus bilangan bulat Rupiah lebih dari 0 (misal 100).');
        }

        if (! is_string($pengaturan['ArahPembulatan'] ?? null) || ArahPembulatan::tryFrom($pengaturan['ArahPembulatan']) === null) {
            $this->Catat('Pengaturan', 'Arah pembulatan tidak dikenal.');
        }

        $persen = self::AmbilDesimal($pengaturan['PersenBiayaLayanan'] ?? null);

        if ($persen === null || $persen->isNegative() || $persen->isGreaterThan(self::PERSEN_BIAYA_LAYANAN_MAKSIMAL)) {
            $this->Catat('Pengaturan', 'Service charge harus 0 sampai '.self::PERSEN_BIAYA_LAYANAN_MAKSIMAL.' persen.');
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
        $daftar = self::AmbilDaftarTeks($isi, $bagian);

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
     * @param  array<mixed>  $isi
     * @return list<string>
     */
    private static function AmbilDaftarTeks(array $isi, string $kunci): array
    {
        $daftar = $isi[$kunci] ?? [];

        return is_array($daftar) ? array_values(array_filter($daftar, 'is_string')) : [];
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
