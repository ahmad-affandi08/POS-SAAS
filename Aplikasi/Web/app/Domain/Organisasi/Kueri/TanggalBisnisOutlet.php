<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Tanggal bisnis sebuah outlet pada suatu waktu (DesainF05a C.1). Zona waktu = `Outlet.ZonaWaktu`; tanpa outlet =
 * `Tenant.ZonaWaktu`. Bila jam lokal masih sebelum `Outlet.JamTutupBuku` (misal 04:00), tanggal bisnisnya hari
 * sebelumnya (transaksi lewat tengah malam masuk hari yang sama). Tanpa outlet tidak ada jam tutup buku (00:00).
 * Hasil = awal hari tanggal bisnis di zona lokal.
 */
final class TanggalBisnisOutlet
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profilTenant,
    ) {}

    public function Hitung(?int $idOutlet, ?CarbonInterface $waktu = null): CarbonImmutable
    {
        if ($idOutlet !== null) {
            $outlet = Outlet::query()->whereKey($idOutlet)->firstOrFail();
            $zona = $outlet->ZonaWaktu;
            $jamTutupBuku = $outlet->JamTutupBuku;
        } else {
            $zona = $this->profilTenant->Ambil($this->konteks->Wajib())['ZonaWaktu'];
            $jamTutupBuku = '00:00';
        }

        $lokal = CarbonImmutable::instance($waktu ?? CarbonImmutable::now())->setTimezone($zona);

        if ($lokal->format('H:i') < self::NormalkanJam($jamTutupBuku)) {
            $lokal = $lokal->subDay();
        }

        return $lokal->startOfDay();
    }

    /** `H:i` dua digit; nilai tidak valid dianggap 00:00 (tanpa pergeseran). */
    private static function NormalkanJam(string $jam): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $jam) === 1 ? substr($jam, 0, 5) : '00:00';
    }
}
