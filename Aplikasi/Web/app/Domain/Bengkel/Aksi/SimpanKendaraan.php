<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Aksi;

use App\Domain\Bengkel\Data\DataKendaraan;
use App\Domain\Bengkel\Layanan\NomorPolisi;
use App\Domain\Bengkel\Model\Kendaraan;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tambah atau ubah kendaraan pelanggan (§9.10). Nomor polisi dinormalisasi (`NomorPolisi`) dan unik per tenant di
 * antara kendaraan aktif: pemeriksaan di sini memberi pesan yang jelas, indeks unik `KunciNomorPolisiAktif` menjaga
 * balapan dua penyimpanan bersamaan. Mengarsipkan (`Aktif` = false) melepas nomornya untuk kendaraan baru, tanpa
 * menghapus riwayat servisnya.
 */
final class SimpanKendaraan
{
    public function __construct(
        private readonly IdentitasPelanggan $pelanggan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataKendaraan $data, int $idPengguna, ?string $uuidKendaraan = null): Kendaraan
    {
        $nomor = NomorPolisi::Normalisasi($data->nomorPolisi)
            ?? throw new PelanggaranAturanBisnis('NomorPolisiTidakValid', 'Nomor polisi tidak valid. Contoh: AD 1234 XY.', 'NomorPolisi');
        $merek = trim($data->merek);

        if ($merek === '') {
            throw new PelanggaranAturanBisnis('MerekWajib', 'Merek kendaraan wajib diisi.', 'Merek');
        }

        $idPelanggan = $this->pelanggan->CariId($data->uuidPelanggan);

        if ($idPelanggan === null || ! $this->pelanggan->CekAktif($idPelanggan)) {
            throw new PelanggaranAturanBisnis('PelangganTidakDikenal', 'Pelanggan tidak ditemukan atau sudah diarsipkan.', 'UuidPelanggan');
        }

        if ($data->tahun !== null && ($data->tahun < 1950 || $data->tahun > now()->year + 1)) {
            throw new PelanggaranAturanBisnis('TahunTidakValid', 'Tahun kendaraan tidak masuk akal.', 'Tahun');
        }

        try {
            return DB::transaction(function () use ($data, $idPengguna, $uuidKendaraan, $nomor, $merek, $idPelanggan): Kendaraan {
                $kendaraan = $uuidKendaraan === null
                    ? new Kendaraan
                    : (Kendaraan::query()->where('Uuid', $uuidKendaraan)->lockForUpdate()->first()
                        ?? throw new PelanggaranAturanBisnis('KendaraanTidakDikenal', 'Kendaraan tidak ditemukan.', 'Umum', 404));
                $lama = $kendaraan->exists ? $kendaraan->only(['NomorPolisi', 'Merek', 'Tipe', 'Tahun', 'IdPelanggan', 'KmTerakhir', 'Aktif']) : null;

                if ($data->aktif && Kendaraan::query()->where('NomorPolisi', $nomor)->where('Aktif', true)->when($kendaraan->exists, fn ($k) => $k->whereKeyNot($kendaraan->Id))->exists()) {
                    throw new PelanggaranAturanBisnis('NomorPolisiSudahAda', "Kendaraan {$nomor} sudah terdaftar. Cari kendaraan itu, atau arsipkan dulu bila sudah berganti pemilik.", 'NomorPolisi');
                }

                $kendaraan->fill([
                    'IdPelanggan' => $idPelanggan,
                    'NomorPolisi' => $nomor,
                    'Merek' => mb_substr($merek, 0, 50),
                    'Tipe' => self::Teks($data->tipe, 80),
                    'Tahun' => $data->tahun,
                    'Warna' => self::Teks($data->warna, 30),
                    'NomorRangka' => self::Teks($data->nomorRangka === null ? null : mb_strtoupper($data->nomorRangka), 40),
                    'NomorMesin' => self::Teks($data->nomorMesin === null ? null : mb_strtoupper($data->nomorMesin), 40),
                    'KmTerakhir' => $data->kmTerakhir,
                    'Catatan' => self::Teks($data->catatan, 255),
                    'Aktif' => $data->aktif,
                ]);

                if (! $kendaraan->exists) {
                    $kendaraan->DibuatOleh = $idPengguna;
                }

                $kendaraan->save();
                $this->audit->Catat($lama === null ? 'bengkel.kendaraan-buat' : 'bengkel.kendaraan-ubah', $kendaraan, $lama, $kendaraan->only(['NomorPolisi', 'Merek', 'Tipe', 'Tahun', 'IdPelanggan', 'KmTerakhir', 'Aktif']), idPengguna: $idPengguna);

                return $kendaraan;
            });
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) === 1062) {
                throw new PelanggaranAturanBisnis('NomorPolisiSudahAda', "Kendaraan {$nomor} sudah terdaftar.", 'NomorPolisi');
            }

            throw $galat;
        }
    }

    private static function Teks(?string $nilai, int $maks): ?string
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? null : mb_substr($teks, 0, $maks);
    }
}
