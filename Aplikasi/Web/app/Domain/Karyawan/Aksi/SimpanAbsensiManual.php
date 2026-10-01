<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Model\Absensi;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-18 (v3.34): pengelola (`karyawan.kelola`) mengoreksi jam absensi dari kasir atau mencatat absensi yang terlewat
 * (karyawan lupa absen, perangkat mati). Jam diisi menurut **waktu outlet** (`Tanggal` = tanggal masuk, `JamKeluar`
 * boleh hari berikutnya untuk shift malam) dan disimpan UTC.
 *
 * Aturan: alasan wajib (5–255 karakter); jam masuk tidak boleh di masa depan; keluar setelah masuk dan paling lama
 * 24 jam; tidak boleh bertumpuk dengan absensi lain karyawan yang sama. Absensi dari kasir tetap ber-`Sumber` `Pos`
 * (swafotonya tetap bukti), yang dicatat di sini ber-`Sumber` `Manual`. Setiap perubahan tercatat: kolom
 * `DikoreksiOleh/Pada/AlasanKoreksi` di baris, nilai lama & baru di audit `absensi.koreksi` / `absensi.tambah-manual`.
 * Rekap gaji (gaji pokok + komisi) tidak membaca absensi, jadi koreksi tidak mengubah gaji yang sudah dibayar.
 */
final class SimpanAbsensiManual
{
    public const BATAS_JAM = 24;

    public function __construct(
        private readonly ZonaWaktuOutlet $zona,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(
        ?Absensi $absensi,
        int $idKaryawan,
        int $idOutlet,
        string $tanggal,
        string $jamMasuk,
        ?string $jamKeluar,
        bool $keluarHariBerikutnya,
        string $alasan,
        int $idPengguna,
    ): Absensi {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan koreksi (minimal 5 karakter).', 'Alasan');
        }

        $tz = $this->zona->Ambil($idOutlet);
        $masuk = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$tanggal} {$jamMasuk}", $tz);
        $keluar = $jamKeluar === null ? null : CarbonImmutable::createFromFormat('Y-m-d H:i', "{$tanggal} {$jamKeluar}", $tz);

        if ($masuk === null || ($jamKeluar !== null && $keluar === null)) {
            throw new PelanggaranAturanBisnis('JamTidakValid', 'Tanggal atau jam tidak valid.', 'JamMasuk');
        }

        $keluar = $keluar !== null && $keluarHariBerikutnya ? $keluar->addDay() : $keluar;

        if ($masuk->greaterThan(CarbonImmutable::now())) {
            throw new PelanggaranAturanBisnis('JamDiMasaDepan', 'Jam masuk tidak boleh di masa depan.', 'JamMasuk');
        }

        if ($keluar !== null && ($keluar->lessThanOrEqualTo($masuk) || $keluar->greaterThan($masuk->addHours(self::BATAS_JAM)))) {
            throw new PelanggaranAturanBisnis('JamKeluarTidakValid', 'Jam keluar harus setelah jam masuk dan paling lama 24 jam kemudian.', 'JamKeluar');
        }

        return DB::transaction(function () use ($absensi, $idKaryawan, $idOutlet, $tanggal, $masuk, $keluar, $alasan, $idPengguna): Absensi {
            Karyawan::query()->whereKey($idKaryawan)->lockForUpdate()->firstOrFail();
            $akhir = $keluar ?? $masuk->addSecond();
            $bertumpuk = Absensi::query()
                ->where('IdKaryawan', $idKaryawan)
                ->when($absensi !== null, fn ($k) => $k->whereKeyNot($absensi?->Id))
                ->where('MasukPada', '<', $akhir->utc())
                ->where(fn ($k) => $k->whereNull('KeluarPada')->orWhere('KeluarPada', '>', $masuk->utc()))
                ->exists();

            if ($bertumpuk) {
                throw new PelanggaranAturanBisnis('AbsensiBertumpuk', 'Jam ini bertumpuk dengan absensi lain karyawan yang sama.', 'JamMasuk');
            }

            $isian = [
                'TanggalBisnis' => $tanggal,
                'MasukPada' => $masuk->utc(),
                'KeluarPada' => $keluar?->utc(),
                'DikoreksiOleh' => $idPengguna,
                'DikoreksiPada' => now(),
                'AlasanKoreksi' => $alasan,
            ];

            if ($absensi === null) {
                $baru = Absensi::query()->create([...$isian, 'IdKaryawan' => $idKaryawan, 'IdOutlet' => $idOutlet, 'Sumber' => Absensi::SUMBER_MANUAL]);
                $this->audit->Catat('absensi.tambah-manual', $baru, nilaiBaru: self::UntukAudit($baru, $alasan), idPengguna: $idPengguna);

                return $baru;
            }

            $terkunci = Absensi::query()->whereKey($absensi->Id)->lockForUpdate()->firstOrFail();
            $lama = self::UntukAudit($terkunci, $terkunci->AlasanKoreksi);
            $terkunci->fill($isian)->save();
            $this->audit->Catat('absensi.koreksi', $terkunci, $lama, self::UntukAudit($terkunci, $alasan), idPengguna: $idPengguna);

            return $terkunci;
        });
    }

    /** @return array<string, string|null> */
    private static function UntukAudit(Absensi $a, ?string $alasan): array
    {
        return [
            'TanggalBisnis' => $a->TanggalBisnis->toDateString(),
            'MasukPada' => $a->MasukPada->toIso8601ZuluString(),
            'KeluarPada' => $a->KeluarPada?->toIso8601ZuluString(),
            'Alasan' => $alasan,
        ];
    }
}
