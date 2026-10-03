<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Data\DataAbsensiWeb;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Enum\StatusWajahKaryawan;
use App\Domain\Karyawan\Layanan\PencocokWajah;
use App\Domain\Karyawan\Layanan\PengukurJarak;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\JadwalKerja;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\WajahKaryawan;
use App\Domain\Organisasi\Kueri\LokasiAbsensiOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-18 bagian 4 (D-37): absen masuk/keluar dari HP pribadi lewat tautan absen. Semua syarat dinilai di server dan
 * yang tidak lolos **ditolak** (tidak ada baris tercatat):
 *
 * 1. Karyawan aktif dengan wajah **Disetujui** pengelola.
 * 2. Lokasi: akurasi GPS tidak lebih buruk dari max(radius outlet, `BatasAkurasiMinimalMeter`), dan jarak ke outlet
 *    ≤ radius. Masuk: outlet terdekat di antara outlet utama karyawan & outlet jadwalnya hari ini (tanpa outlet utama
 *    = semua outlet); keluar: outlet absensi masuknya.
 * 3. Wajah: kemiripan sidik wajah saat ini dengan sidik terdaftar ≥ `AmbangKemiripanWajah`. Swafoto disimpan sebagai
 *    bukti. Wajah tidak cocok dicatat di log audit (tanpa sidiknya) sebagai jejak percobaan.
 *
 * Idempoten per Uuid: masuk dengan Uuid yang sudah tercatat atau keluar untuk absensi yang sudah ditutup mengembalikan
 * absensi itu tanpa mengubah apa pun.
 */
final class CatatAbsensiWeb
{
    public function __construct(
        private readonly LokasiAbsensiOutlet $lokasi,
        private readonly PengukurJarak $pengukur,
        private readonly PencocokWajah $pencocok,
        private readonly PenyimpanSwafoto $swafoto,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PencatatAudit $audit,
    ) {}

    public function Masuk(Karyawan $karyawan, DataAbsensiWeb $data): Absensi
    {
        $lama = Absensi::query()->where('Uuid', $data->uuid)->first();

        if ($lama !== null) {
            return $lama->IdKaryawan === $karyawan->Id
                ? $lama
                : throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Muat ulang halaman lalu absen lagi.', 'Uuid');
        }

        $this->PastikanBolehAbsen($karyawan);

        if (Absensi::query()->where('IdKaryawan', $karyawan->Id)->whereNull('KeluarPada')->exists()) {
            throw new PelanggaranAturanBisnis('SudahAbsenMasuk', 'Anda sudah absen masuk. Absen keluar dulu sebelum masuk lagi.', 'Absensi');
        }

        $sekarang = CarbonImmutable::now();
        [$outlet, $jarak] = $this->PilihOutlet($karyawan, $data, $this->AmbilIdOutletBoleh($karyawan, $sekarang));
        $kemiripan = $this->CocokkanWajah($karyawan, $data);
        $path = $this->swafoto->Simpan($karyawan->IdTenant, $data->swafoto);

        try {
            return DB::transaction(fn (): Absensi => Absensi::query()->create([
                'Uuid' => $data->uuid,
                'IdKaryawan' => $karyawan->Id,
                'IdOutlet' => $outlet['Id'],
                'IdPerangkat' => null,
                'TanggalBisnis' => $this->tanggalBisnis->Hitung($outlet['Id'], $sekarang)->toDateString(),
                'MasukPada' => $sekarang->utc(),
                'PathSwafotoMasuk' => $path,
                'Sumber' => Absensi::SUMBER_WEB,
                'LintangMasuk' => $data->lintang,
                'BujurMasuk' => $data->bujur,
                'AkurasiMasukMeter' => $data->akurasiMeter,
                'JarakMasukMeter' => $jarak,
                'KemiripanWajahMasuk' => (string) $kemiripan,
            ]), 3);
        } catch (Throwable $galat) {
            $this->swafoto->Hapus($path);

            throw $galat;
        }
    }

    public function Keluar(Karyawan $karyawan, DataAbsensiWeb $data): Absensi
    {
        $absensi = Absensi::query()->where('Uuid', $data->uuid)->where('IdKaryawan', $karyawan->Id)->first();

        if ($absensi === null) {
            throw new PelanggaranAturanBisnis('BelumAbsenMasuk', 'Absensi masuk tidak ditemukan. Muat ulang halaman.', 'Absensi');
        }

        if ($absensi->KeluarPada !== null) {
            return $absensi;
        }

        $this->PastikanBolehAbsen($karyawan);
        [, $jarak] = $this->PilihOutlet($karyawan, $data, [$absensi->IdOutlet]);
        $kemiripan = $this->CocokkanWajah($karyawan, $data);
        $path = $this->swafoto->Simpan($karyawan->IdTenant, $data->swafoto);

        try {
            return DB::transaction(function () use ($absensi, $data, $jarak, $kemiripan, $path): Absensi {
                $terkini = Absensi::query()->lockForUpdate()->findOrFail($absensi->Id);

                if ($terkini->KeluarPada !== null) {
                    $this->swafoto->Hapus($path);

                    return $terkini;
                }

                $terkini->forceFill([
                    'KeluarPada' => now()->utc(),
                    'PathSwafotoKeluar' => $path,
                    'LintangKeluar' => $data->lintang,
                    'BujurKeluar' => $data->bujur,
                    'AkurasiKeluarMeter' => $data->akurasiMeter,
                    'JarakKeluarMeter' => $jarak,
                    'KemiripanWajahKeluar' => (string) $kemiripan,
                ])->save();

                return $terkini;
            }, 3);
        } catch (Throwable $galat) {
            $this->swafoto->Hapus($path);

            throw $galat;
        }
    }

    private function PastikanBolehAbsen(Karyawan $karyawan): void
    {
        if ($karyawan->Status !== StatusKaryawan::Aktif) {
            throw new PelanggaranAturanBisnis('KaryawanNonaktif', "{$karyawan->Nama} sudah nonaktif sebagai karyawan.", 'Karyawan');
        }
    }

    /**
     * Outlet utama karyawan + outlet jadwalnya hari ini; tanpa outlet utama = semua outlet.
     *
     * @return list<int>|null
     */
    private function AmbilIdOutletBoleh(Karyawan $karyawan, CarbonImmutable $sekarang): ?array
    {
        if ($karyawan->IdOutlet === null) {
            return null;
        }

        $jadwal = JadwalKerja::query()
            ->where('IdKaryawan', $karyawan->Id)
            ->whereBetween('Tanggal', [$sekarang->subDay()->toDateString(), $sekarang->addDay()->toDateString()])
            ->pluck('IdOutlet')
            ->all();

        return array_values(array_unique([$karyawan->IdOutlet, ...array_map('intval', $jadwal)]));
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @return array{0: array{Id: int, Nama: string, Lintang: string, Bujur: string, RadiusMeter: int}, 1: int}
     */
    private function PilihOutlet(Karyawan $karyawan, DataAbsensiWeb $data, ?array $idOutlet): array
    {
        $daftar = $this->lokasi->Ambil($idOutlet);

        if ($daftar === []) {
            throw new PelanggaranAturanBisnis('OutletTanpaLokasi', 'Lokasi outlet Anda belum diatur. Minta pengelola mengisi titik lokasi outlet.', 'Lokasi');
        }

        $terdekat = null;
        $jarakTerdekat = PHP_INT_MAX;

        foreach ($daftar as $outlet) {
            $jarak = $this->pengukur->HitungMeter($outlet['Lintang'], $outlet['Bujur'], $data->lintang, $data->bujur);

            if ($jarak < $jarakTerdekat) {
                [$terdekat, $jarakTerdekat] = [$outlet, $jarak];
            }
        }

        if ($terdekat === null) {
            throw new PelanggaranAturanBisnis('OutletTanpaLokasi', 'Lokasi outlet Anda belum diatur. Minta pengelola mengisi titik lokasi outlet.', 'Lokasi');
        }

        $batasAkurasi = max($terdekat['RadiusMeter'], (int) config('karyawan.BatasAkurasiMinimalMeter'));

        if ($data->akurasiMeter > $batasAkurasi) {
            throw new PelanggaranAturanBisnis('AkurasiLokasiRendah', "Sinyal lokasi lemah (±{$data->akurasiMeter} m). Nyalakan GPS, dekati pintu atau jendela, lalu coba lagi.", 'Lokasi');
        }

        if ($jarakTerdekat > $terdekat['RadiusMeter']) {
            $this->audit->Catat('absensi.web.di-luar-radius', $karyawan, null, ['Outlet' => $terdekat['Nama'], 'JarakMeter' => $jarakTerdekat, 'RadiusMeter' => $terdekat['RadiusMeter']]);

            throw new PelanggaranAturanBisnis('DiLuarRadius', "Anda berada ±{$jarakTerdekat} m dari {$terdekat['Nama']}. Absen hanya bisa dalam radius {$terdekat['RadiusMeter']} m dari outlet.", 'Lokasi');
        }

        return [$terdekat, $jarakTerdekat];
    }

    private function CocokkanWajah(Karyawan $karyawan, DataAbsensiWeb $data): BigDecimal
    {
        $wajah = WajahKaryawan::query()->where('IdKaryawan', $karyawan->Id)->where('Status', StatusWajahKaryawan::Disetujui->value)->first();

        if ($wajah === null) {
            throw new PelanggaranAturanBisnis('WajahBelumDisetujui', 'Wajah Anda belum terdaftar atau belum disetujui pengelola.', 'Wajah');
        }

        $kemiripan = $this->pencocok->HitungKemiripan($this->pencocok->Validasi($data->sidikWajah), $wajah->SidikWajah);

        if (! $this->pencocok->CekCocok($kemiripan)) {
            $this->audit->Catat('absensi.web.wajah-tidak-cocok', $karyawan, null, ['Kemiripan' => (string) $kemiripan]);

            throw new PelanggaranAturanBisnis('WajahTidakCocok', 'Wajah tidak cocok dengan wajah terdaftar. Hadapkan wajah lurus ke kamera di tempat terang, lalu coba lagi.', 'Wajah');
        }

        return $kemiripan;
    }
}
