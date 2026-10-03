<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Enum\HasilKunjungan;
use App\Domain\Penjualan\Model\KunjunganSales;
use App\Domain\Penjualan\Model\PesananGrosir;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Terima item outbox `Kunjungan.Catat` (Modul Salesman bagian 1, §9.7, SLS-11): satu item per kunjungan, dikirim saat
 * check-out dengan seluruh datanya. Idempoten per Uuid (= `KunjunganSales.Uuid`).
 *
 * - Kunjungan sudah terjadi, jadi hanya bentuk yang tidak mungkin benar yang ditolak: waktu di masa depan atau keluar
 *   sebelum masuk, pengguna bukan anggota usaha, pelanggan tidak dikenal. Izin/outlet salesman yang berubah setelah
 *   offline tidak menolak catatan (sama dengan `TerimaBahanTerbuangPos`).
 * - `UuidPesananGrosir` yang belum ada di server disimpan kosong, tidak menggagalkan item: pesanan dari kunjungan itu
 *   biasanya dikirim lebih dulu di batch yang sama (FIFO), dan bila tiba belakangan `TerimaPesananGrosirPos` menautkannya
 *   lewat `UuidKunjungan`.
 * - Lokasi diterima sebagai string desimal yang sudah divalidasi rentangnya oleh penangan; disimpan apa adanya.
 */
final class TerimaKunjunganSalesPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AnggotaOutlet $anggota,
        private readonly IdentitasPelanggan $identitas,
        private readonly ZonaWaktuOutlet $zonaWaktu,
    ) {}

    public function Jalankan(
        string $uuid,
        int $idPerangkat,
        int $idOutlet,
        string $uuidPelanggan,
        string $uuidPengguna,
        CarbonImmutable $masukPada,
        ?CarbonImmutable $keluarPada,
        ?string $latitude,
        ?string $longitude,
        ?int $akurasiMeter,
        HasilKunjungan $hasil,
        ?string $catatan,
        ?string $uuidPesananGrosir,
    ): StatusItemSinkron {
        $batas = CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK);

        if ($masukPada->greaterThan($batas) || $keluarPada?->greaterThan($batas) === true) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu kunjungan ada di masa depan. Periksa jam perangkat.', 'MasukPada');
        }

        if ($keluarPada !== null && $keluarPada->lessThan($masukPada)) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu keluar lebih awal dari waktu masuk.', 'KeluarPada');
        }

        if (($lama = $this->BandingkanLama($uuid, $uuidPelanggan, $hasil)) !== null) {
            return $lama;
        }

        $idTenant = $this->konteks->Wajib();
        [$salesman] = $this->anggota->CariDiTenant($idTenant, $uuidPengguna, $idOutlet)
            ?? throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Salesman ini bukan anggota usaha ini.', 'UuidPengguna');
        $idPelanggan = $this->identitas->CariId($uuidPelanggan)
            ?? throw new PelanggaranAturanBisnis('PelangganTidakDikenal', 'Pelanggan tidak ditemukan. Perbarui data pelanggan di aplikasi.', 'UuidPelanggan');
        $idPesanan = $uuidPesananGrosir === null ? null : PesananGrosir::query()->where('Uuid', $uuidPesananGrosir)->value('Id');

        try {
            return DB::transaction(function () use ($uuid, $idPerangkat, $idOutlet, $idPelanggan, $salesman, $masukPada, $keluarPada, $latitude, $longitude, $akurasiMeter, $hasil, $catatan, $idPesanan): StatusItemSinkron {
                KunjunganSales::query()->create([
                    'Uuid' => $uuid,
                    'IdOutlet' => $idOutlet,
                    'IdPelanggan' => $idPelanggan,
                    'IdPengguna' => $salesman->id,
                    'IdPerangkat' => $idPerangkat,
                    'Tanggal' => $masukPada->setTimezone($this->zonaWaktu->Ambil($idOutlet))->toDateString(),
                    'MasukPada' => $masukPada,
                    'KeluarPada' => $keluarPada,
                    'Latitude' => $latitude,
                    'Longitude' => $longitude,
                    'AkurasiMeter' => $akurasiMeter,
                    'Hasil' => $hasil,
                    'Catatan' => $catatan,
                    'IdPesananGrosir' => is_int($idPesanan) ? $idPesanan : null,
                    'DiterimaPada' => CarbonImmutable::now(),
                ]);

                return StatusItemSinkron::Diterima;
            });
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) !== 1062) {
                throw $galat;
            }

            // Kiriman ganda bersamaan: pemenang sudah tersimpan; Uuid bentrok dengan data lain = ditolak.
            $lama = $this->BandingkanLama($uuid, $uuidPelanggan, $hasil);

            if ($lama === null) {
                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik ini sudah dipakai data lain. Buat ulang data di aplikasi.', 'Uuid');
            }

            return $lama;
        }
    }

    /**
     * Uuid yang sudah ada: `Duplikat` bila pelanggan & hasilnya sama (kirim ulang), selain itu `UuidSudahDipakai` (kontrak
     * `PenanganItemSinkron`); null bila Uuid belum ada.
     * Membaca basis data, jadi hasil panggilan kedua (setelah bentrok unik) bisa berbeda dengan yang pertama.
     *
     * @phpstan-impure
     */
    private function BandingkanLama(string $uuid, string $uuidPelanggan, HasilKunjungan $hasil): ?StatusItemSinkron
    {
        $lama = KunjunganSales::query()->where('Uuid', $uuid)->first();

        if ($lama === null) {
            return null;
        }

        if ($lama->IdPelanggan === $this->identitas->CariId($uuidPelanggan) && $lama->Hasil === $hasil) {
            return StatusItemSinkron::Duplikat;
        }

        throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik ini sudah dipakai data lain dengan isi berbeda. Buat ulang data di aplikasi.', 'Uuid');
    }
}
