<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Layanan\KodeLayarAbsensi;
use App\Domain\Organisasi\Model\Outlet;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Titik lokasi & radius absensi outlet aktif (F-18 bagian 4, D-37) untuk geofence absensi web. Outlet tanpa titik
 * lokasi tidak ikut: tidak ada yang bisa dibuktikan tanpa titik acuan. `WajibQr` = absen di outlet itu wajib menyertakan
 * kode layar QR yang berlaku ({@see KodeLayarAbsensi}).
 */
final class LokasiAbsensiOutlet
{
    public function __construct(private readonly KodeLayarAbsensi $kodeLayar) {}

    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet aktif tenant
     * @return list<array{Id: int, Nama: string, Lintang: string, Bujur: string, RadiusMeter: int, WajibQr: bool}>
     */
    public function Ambil(?array $idOutlet = null): array
    {
        return array_values(Outlet::query()
            ->where('Status', StatusOrganisasi::Aktif->value)
            ->whereNotNull('Lintang')
            ->whereNotNull('Bujur')
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('Id', $idOutlet ?? []))
            ->orderBy('Id')
            ->get(['Id', 'Nama', 'Lintang', 'Bujur', 'RadiusAbsensiMeter', 'WajibQrAbsensi'])
            ->map(fn (Outlet $o): array => [
                'Id' => $o->Id,
                'Nama' => $o->Nama,
                'Lintang' => (string) $o->Lintang,
                'Bujur' => (string) $o->Bujur,
                'RadiusMeter' => $o->RadiusAbsensiMeter,
                'WajibQr' => $o->WajibQrAbsensi,
            ])
            ->all());
    }

    /** Nama outlet aktif (untuk pesan & status halaman absen). */
    public function AmbilNama(int $idOutlet): ?string
    {
        $nama = Outlet::query()->whereKey($idOutlet)->value('Nama');

        return is_string($nama) ? $nama : null;
    }

    /**
     * Ada outlet aktif (dari daftar, null = semua) yang mewajibkan kode layar QR; dipakai halaman absen.
     *
     * @param  list<int>|null  $idOutlet
     */
    public function CekAdaWajibQr(?array $idOutlet): bool
    {
        return Outlet::query()
            ->where('Status', StatusOrganisasi::Aktif->value)
            ->where('WajibQrAbsensi', true)
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('Id', $idOutlet ?? []))
            ->exists();
    }

    public function CekKodeQr(int $idOutlet, string $kode, CarbonInterface $waktu): bool
    {
        $outlet = Outlet::query()->find($idOutlet);

        return $outlet instanceof Outlet && $this->kodeLayar->Cocokkan($outlet, $kode, $waktu);
    }
}
