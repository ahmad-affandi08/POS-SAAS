<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Karyawan\Data\DataKaryawan;
use App\Domain\Organisasi\Aksi\TambahPengguna;
use App\Domain\Organisasi\Data\DataAksesAnggota;
use App\Domain\Organisasi\Data\DataPenggunaBaru;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\DB;

/**
 * Audit kemudahan pakai #34 (F-02, F-18): satu "Tambah staf" — akun pengguna (`TambahPengguna`, D-22) dan, bila
 * diminta, data karyawan yang tertaut ke akun itu (`SimpanKaryawan`, untuk jadwal, absensi, komisi, gaji) dalam satu
 * transaksi. Outlet karyawan = outlet pertama yang ditugaskan (kosong bila semua outlet).
 */
final class TambahStaf
{
    public function __construct(
        private readonly TambahPengguna $tambahPengguna,
        private readonly SimpanKaryawan $simpanKaryawan,
    ) {}

    public function Jalankan(Pengguna $pelaku, DataPenggunaBaru $data, DataAksesAnggota $akses, bool $jugaKaryawan, ?string $jabatan): Pengguna
    {
        return DB::transaction(function () use ($pelaku, $data, $akses, $jugaKaryawan, $jabatan): Pengguna {
            $pengguna = $this->tambahPengguna->Jalankan($pelaku, $data, $akses);

            if ($jugaKaryawan) {
                $this->simpanKaryawan->Jalankan(new DataKaryawan(
                    nama: $pengguna->Nama,
                    jabatan: $jabatan,
                    levelStaf: null,
                    gajiPokok: null,
                    uuidPengguna: $pengguna->Uuid,
                    uuidOutlet: $akses->semuaOutlet ? null : ($akses->uuidOutlet[0] ?? null),
                    idPengguna: $pelaku->Id,
                ));
            }

            return $pengguna;
        });
    }
}
