<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Outlet;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * F-18 bagian 4 (D-37): titik lokasi & radius absensi web outlet. Lintang & bujur keduanya diisi atau keduanya kosong
 * (kosong = outlet tidak bisa dipakai absen web). Radius 20–1.000 m. Audit `outlet.lokasi-absensi.ubah`.
 */
final class AturLokasiAbsensiOutlet
{
    public const RADIUS_MINIMAL = 20;

    public const RADIUS_MAKSIMAL = 1000;

    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Outlet $outlet, ?string $lintang, ?string $bujur, int $radiusMeter): Outlet
    {
        if (($lintang === null) !== ($bujur === null)) {
            throw new PelanggaranAturanBisnis('KoordinatTidakLengkap', 'Isi lintang dan bujur sekaligus, atau kosongkan keduanya.', 'Lintang');
        }

        if ($radiusMeter < self::RADIUS_MINIMAL || $radiusMeter > self::RADIUS_MAKSIMAL) {
            throw new PelanggaranAturanBisnis('RadiusTidakSah', 'Radius absensi antara '.self::RADIUS_MINIMAL.' dan '.self::RADIUS_MAKSIMAL.' meter.', 'RadiusAbsensiMeter');
        }

        $lintangBaru = $lintang === null ? null : self::Normalkan($lintang, 90, 'Lintang');
        $bujurBaru = $bujur === null ? null : self::Normalkan($bujur, 180, 'Bujur');

        return DB::transaction(function () use ($outlet, $lintangBaru, $bujurBaru, $radiusMeter): Outlet {
            $outlet = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);
            $lama = ['Lintang' => $outlet->Lintang, 'Bujur' => $outlet->Bujur, 'RadiusAbsensiMeter' => $outlet->RadiusAbsensiMeter];
            $baru = ['Lintang' => $lintangBaru, 'Bujur' => $bujurBaru, 'RadiusAbsensiMeter' => $radiusMeter];

            if ($lama !== $baru) {
                $outlet->forceFill($baru)->save();
                $this->audit->Catat('outlet.lokasi-absensi.ubah', $outlet, nilaiLama: $lama, nilaiBaru: $baru);
            }

            return $outlet;
        });
    }

    private static function Normalkan(string $nilai, int $batas, string $bidang): string
    {
        $angka = BigDecimal::of($nilai);

        if ($angka->abs()->isGreaterThan($batas)) {
            throw new PelanggaranAturanBisnis('KoordinatTidakSah', "{$bidang} harus antara -{$batas} dan {$batas}.", $bidang);
        }

        return (string) $angka->toScale(7, RoundingMode::HalfUp);
    }
}
