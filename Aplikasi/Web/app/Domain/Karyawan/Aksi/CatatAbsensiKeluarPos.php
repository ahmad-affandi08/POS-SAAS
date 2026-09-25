<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Karyawan\Data\DataAbsensiPos;
use App\Domain\Karyawan\Layanan\PenyimpanSwafoto;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Absen keluar dari aplikasi kasir (item outbox `Absensi.Keluar`, F-18): mengisi `KeluarPada` absensi masuknya sekali
 * (sudah terisi = `Duplikat`). Absensi masuk harus milik karyawan pengguna yang sama (dikirim lebih dulu, outbox FIFO)
 * dan waktu keluar tidak sebelum waktu masuk.
 */
final class CatatAbsensiKeluarPos
{
    public function __construct(
        private readonly AnggotaOutlet $anggota,
        private readonly PenyimpanSwafoto $swafoto,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis KasirTidakDitemukan, AbsensiTidakDitemukan, WaktuTidakValid, SwafotoTidakValid
     */
    public function Jalankan(DataAbsensiPos $data): StatusItemSinkron
    {
        $pelaku = $this->anggota->Cari($data->idTenant, $data->uuidPengguna, $data->idOutlet);

        if ($pelaku === null) {
            throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Pengguna ini tidak terdaftar di outlet perangkat.', 'UuidPengguna');
        }

        $karyawan = Karyawan::query()->where('IdPengguna', $pelaku->id)->first();
        $absensi = $karyawan === null ? null : Absensi::query()->where('Uuid', (string) $data->uuidAbsensi)->where('IdKaryawan', $karyawan->Id)->first();

        if ($absensi === null) {
            throw new PelanggaranAturanBisnis('AbsensiTidakDitemukan', 'Absen masuk untuk absen keluar ini tidak ditemukan.', 'UuidAbsensi');
        }

        if ($absensi->KeluarPada !== null) {
            return StatusItemSinkron::Duplikat;
        }

        if ($data->waktu->lessThan($absensi->MasukPada)) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu keluar tidak boleh sebelum waktu masuk.', 'KeluarPada');
        }

        $path = $data->swafoto === null ? null : $this->swafoto->Simpan($data->idTenant, $data->swafoto);

        try {
            return DB::transaction(function () use ($absensi, $data, $path): StatusItemSinkron {
                $terkunci = Absensi::query()->whereKey($absensi->Id)->lockForUpdate()->firstOrFail();

                if ($terkunci->KeluarPada !== null) {
                    $this->swafoto->Hapus($path);

                    return StatusItemSinkron::Duplikat;
                }

                $terkunci->fill(['KeluarPada' => $data->waktu->utc(), 'PathSwafotoKeluar' => $path])->save();

                return StatusItemSinkron::Diterima;
            }, 3);
        } catch (Throwable $galat) {
            $this->swafoto->Hapus($path);

            throw $galat;
        }
    }
}
