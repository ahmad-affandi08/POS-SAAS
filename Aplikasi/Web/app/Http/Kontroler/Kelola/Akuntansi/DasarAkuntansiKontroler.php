<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Data\SaringLaporanKeuangan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Bantuan bersama kontroler akuntansi F-13a: izin ubah (`akuntansi.kelola`) untuk props halaman, opsi outlet sesuai
 * akses pelaku, dan saringan laporan dari query (`dari`, `sampai` `YYYY-MM-DD`, `outlet` Uuid). Tanggal tidak sah
 * jatuh ke bulan berjalan; outlet di luar akses = 404.
 */
abstract class DasarAkuntansiKontroler extends DasarKelolaKontroler
{
    /** Rentang laporan paling panjang (hari) agar kueri tetap ringan. */
    private const MAKS_HARI_LAPORAN = 1830;

    protected function CekIzinKelola(): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::AkuntansiKelola);
    }

    /**
     * @return list<array{Uuid: string, Nama: string}>
     */
    protected function AmbilOpsiOutlet(): array
    {
        return array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], app(PetaUuidOutlet::class)->AmbilRingkas($this->IdOutletBoleh()));
    }

    protected function AmbilSaringLaporan(Request $permintaan): SaringLaporanKeuangan
    {
        $hariIni = CarbonImmutable::today();
        $dari = self::BacaTanggal($permintaan->query('dari')) ?? $hariIni->startOfMonth();
        $sampai = self::BacaTanggal($permintaan->query('sampai')) ?? $hariIni;

        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        if ($dari->diffInDays($sampai) > self::MAKS_HARI_LAPORAN) {
            $dari = $sampai->subDays(self::MAKS_HARI_LAPORAN);
        }

        $uuidOutlet = $permintaan->query('outlet');
        $idOutlet = is_string($uuidOutlet) && $uuidOutlet !== '' ? $this->CariOutlet($uuidOutlet)->Id : null;

        return new SaringLaporanKeuangan($dari->toDateString(), $sampai->toDateString(), $idOutlet, $this->IdOutletBoleh());
    }

    /**
     * @return array{Dari: string, Sampai: string, Outlet: string}
     */
    protected static function PetakanSaring(SaringLaporanKeuangan $saring, Request $permintaan): array
    {
        $outlet = $permintaan->query('outlet');

        return ['Dari' => $saring->dari, 'Sampai' => $saring->sampai, 'Outlet' => $saring->idOutlet !== null && is_string($outlet) ? $outlet : ''];
    }

    private static function BacaTanggal(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) !== 1) {
            return null;
        }

        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $nilai);

        return $tanggal instanceof CarbonImmutable && $tanggal->toDateString() === $nilai ? $tanggal : null;
    }
}
