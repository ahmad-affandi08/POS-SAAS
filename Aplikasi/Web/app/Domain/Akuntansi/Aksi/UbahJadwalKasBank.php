<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Model\JadwalKasBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * D-23 D: hentikan/aktifkan lagi jadwal kas berulang atau ubah jumlahnya (misal tarif sewa naik). Transaksi yang sudah
 * tercatat tidak berubah. Mengaktifkan lagi tidak mencatat tanggal yang terlewat: jatuh tempo dimajukan ke tanggal
 * pertama sejak hari ini. Audit `kas-bank.jadwal-ubah`.
 */
final class UbahJadwalKasBank
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(JadwalKasBank $jadwal, ?bool $aktif, ?Uang $jumlah, CarbonImmutable $hariIni, int $idPengguna): JadwalKasBank
    {
        if ($jumlah !== null && $jumlah->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah harus lebih dari Rp 0.', 'Jumlah');
        }

        return DB::transaction(function () use ($jadwal, $aktif, $jumlah, $hariIni, $idPengguna): JadwalKasBank {
            $terkunci = JadwalKasBank::query()->whereKey($jadwal->Id)->lockForUpdate()->firstOrFail();
            $lama = ['Aktif' => $terkunci->Aktif, 'Jumlah' => $terkunci->Jumlah];

            if ($jumlah !== null) {
                $terkunci->Jumlah = $jumlah->KeString();
            }

            if ($aktif !== null && $aktif !== $terkunci->Aktif) {
                $terkunci->Aktif = $aktif;
                $terkunci->GalatTerakhir = null;

                if ($aktif) {
                    $acuan = CarbonImmutable::parse($terkunci->TanggalAcuan->toDateString());
                    $berikutnya = CarbonImmutable::parse($terkunci->TanggalBerikutnya->toDateString());

                    while ($berikutnya->toDateString() < $hariIni->toDateString()) {
                        $berikutnya = $terkunci->Frekuensi->HitungBerikutnya($acuan, $berikutnya);
                    }

                    $terkunci->setAttribute('TanggalBerikutnya', $berikutnya->toDateString());
                }
            }

            if ($terkunci->isDirty()) {
                $terkunci->save();
                $this->audit->Catat('kas-bank.jadwal-ubah', $terkunci, nilaiLama: $lama, nilaiBaru: ['Aktif' => $terkunci->Aktif, 'Jumlah' => $terkunci->Jumlah], idPengguna: $idPengguna);
            }

            return $terkunci;
        });
    }
}
