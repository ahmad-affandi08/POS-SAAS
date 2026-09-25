<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Kueri\ShiftBelumDitutup;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-15 tutup bulan: mengunci periode `YYYY-MM` (tabel `KunciPeriode`). Setelah dikunci, transaksi back-office bertanggal
 * di periode itu ditolak (`PeriodeTerkunci`); transaksi POS offline tetap diterima dan dibukukan di periode terbuka
 * berikutnya (§18). Syarat: periode sudah lewat (bulan berjalan menurut zona waktu tenant tidak bisa dikunci) dan
 * semua shift sampai akhir periode sudah ditutup. Audit `akuntansi.periode.kunci`.
 */
final class KunciPeriodeAkuntansi
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly ShiftBelumDitutup $shift,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $periode, int $idPengguna): KunciPeriode
    {
        $awal = self::UraiPeriode($periode);
        $idTenant = $this->konteks->Wajib();
        $bulanIni = CarbonImmutable::now($this->profil->Ambil($idTenant)['ZonaWaktu'])->startOfMonth();
        $label = PenjagaKunciPeriode::FormatPeriode($periode);

        if ($awal->gte($bulanIni)) {
            throw new PelanggaranAturanBisnis('PeriodeBelumBerakhir', "Periode {$label} belum berakhir. Periode hanya bisa dikunci setelah bulannya lewat.", 'Periode');
        }

        return DB::transaction(function () use ($periode, $awal, $idPengguna, $label): KunciPeriode {
            if (KunciPeriode::query()->where('Periode', $periode)->lockForUpdate()->exists()) {
                throw new PelanggaranAturanBisnis('PeriodeSudahTerkunci', "Periode {$label} sudah dikunci.", 'Periode');
            }

            $terbuka = $this->shift->Hitung($awal->endOfMonth());

            if ($terbuka > 0) {
                throw new PelanggaranAturanBisnis(
                    'ShiftBelumDitutup',
                    "Masih ada {$terbuka} shift yang belum ditutup sampai akhir {$label}. Tutup shift di aplikasi kasir dulu, lalu kunci periode.",
                    'Periode',
                    detail: ['Jumlah' => $terbuka],
                );
            }

            $kunci = KunciPeriode::query()->create([
                'Periode' => $periode,
                'DikunciPada' => now(),
                'DikunciOleh' => $idPengguna,
            ]);
            $this->audit->Catat('akuntansi.periode.kunci', $kunci, nilaiBaru: ['Periode' => $periode]);

            return $kunci;
        });
    }

    /** `YYYY-MM` → awal bulan; format salah = galat bisnis. */
    public static function UraiPeriode(string $periode): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode harus berformat tahun-bulan, misal 2026-09.', 'Periode');
        }

        return CarbonImmutable::createFromFormat('!Y-m', $periode) ?: throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode tidak valid.', 'Periode');
    }
}
