<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Tindakan\Kueri\KotakTindakan;
use App\Domain\Laporan\Kueri\DasborAplikasiPemilik;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Enum\JenisNotifikasiPengguna;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Kueri\DaftarPerangkat;
use App\Domain\Organisasi\Kueri\KonteksTindakanPengguna;
use App\Domain\Organisasi\Model\PerangkatPengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use Carbon\CarbonImmutable;

/** Membuat ringkasan push harian untuk kondisi operasional penting yang masih terbuka. */
final class BuatNotifikasiOperasional
{
    public function __construct(
        private readonly KonteksTindakanPengguna $konteks,
        private readonly KotakTindakan $kotak,
        private readonly DaftarPerangkat $perangkat,
        private readonly BuatNotifikasiPengguna $buat,
    ) {}

    public function Jalankan(int $idTenant, CarbonImmutable $hariIni): int
    {
        $idPengguna = PerangkatPengguna::query()->where('Aktif', true)->distinct()->pluck('IdPengguna');
        $anggota = TenantPengguna::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->whereIn('IdPengguna', $idPengguna)
            ->pluck('IdPengguna');
        $dibuat = 0;

        foreach ($anggota as $id) {
            $konteks = $this->konteks->Buat($idTenant, (int) $id, $hariIni);
            foreach ($this->kotak->Ambil($konteks) as $butir) {
                $jenis = match ($butir->kunci) {
                    'shift.tinjauan' => JenisNotifikasiPengguna::SelisihKas,
                    'stok.kritis' => JenisNotifikasiPengguna::StokKritis,
                    'piutang.lewat-jatuh-tempo' => JenisNotifikasiPengguna::PiutangJatuhTempo,
                    default => null,
                };

                if ($jenis !== null) {
                    $dibuat += (int) $this->buat->Jalankan(
                        (int) $id,
                        $jenis,
                        'Operasional:'.$jenis->value.':'.$hariIni->toDateString(),
                        $butir->judul,
                        $butir->jumlah.' butir. '.$butir->keterangan,
                        ['Tautan' => 'notifikasi'],
                    )->wasRecentlyCreated;
                }
            }

            if ($konteks->CekIzin(IzinTenant::PerangkatLihat->value)) {
                $jumlah = count($this->perangkat->AmbilTidakAktif(
                    $konteks->idOutletBoleh,
                    CarbonImmutable::now()->subMinutes(DasborAplikasiPemilik::MENIT_PERANGKAT_TIDAK_AKTIF),
                ));
                if ($jumlah > 0) {
                    $dibuat += (int) $this->buat->Jalankan(
                        (int) $id,
                        JenisNotifikasiPengguna::PerangkatOffline,
                        'Operasional:PerangkatOffline:'.$hariIni->toDateString(),
                        'Perangkat POS tidak aktif',
                        $jumlah.' perangkat tidak terhubung lebih dari 30 menit.',
                        ['Tautan' => 'perangkat'],
                    )->wasRecentlyCreated;
                }
            }
        }

        return $dibuat;
    }
}
