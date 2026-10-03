<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Layanan\KodeLayarAbsensi;
use App\Domain\Organisasi\Model\Outlet;
use Carbon\CarbonInterface;

/**
 * F-18 bagian 4 (D-37): isi layar QR absensi outlet `/{slug}/layar-absen/{token}` (nama outlet, kode berlaku, QR SVG).
 * Tautan tak dikenal, dicabut, atau outlet diarsipkan = null.
 */
final class LayarAbsensiOutlet
{
    public function __construct(private readonly KodeLayarAbsensi $kode) {}

    /** @return array{NamaOutlet: string, WajibQr: bool, Kode: string, BerlakuSampai: string, Qr: string}|null */
    public function Ambil(string $token, CarbonInterface $waktu): ?array
    {
        if (strlen($token) !== KodeLayarAbsensi::PANJANG_TOKEN) {
            return null;
        }

        $outlet = Outlet::query()
            ->where('HashTokenLayarAbsen', KodeLayarAbsensi::Hash($token))
            ->where('Status', StatusOrganisasi::Aktif->value)
            ->first();

        if (! $outlet instanceof Outlet || ! is_string($outlet->TokenLayarAbsen)) {
            return null;
        }

        return [
            'NamaOutlet' => $outlet->Nama,
            'WajibQr' => $outlet->WajibQrAbsensi,
            ...$this->kode->AmbilUntukLayar($outlet->TokenLayarAbsen, $waktu),
        ];
    }
}
