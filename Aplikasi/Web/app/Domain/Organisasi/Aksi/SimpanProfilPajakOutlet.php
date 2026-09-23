<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataProfilPajakOutlet;
use App\Domain\Organisasi\Model\Outlet;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 3: pengaturan pajak outlet. Digabung ke `Outlet.ProfilPajak` (kunci lain seperti NITKU tetap).
 * Tidak menyimpan angka tarif: tarif selalu dicari dari `TarifPajak` bertanggal (CLAUDE.md #12).
 */
final class SimpanProfilPajakOutlet
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Outlet $outlet, DataProfilPajakOutlet $data): Outlet
    {
        $persen = self::AmbilPersen($data->persenBiayaLayanan);

        return DB::transaction(function () use ($outlet, $data, $persen): Outlet {
            $outlet = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);
            $lama = $outlet->ProfilPajak ?? [];

            $baru = [
                ...$lama,
                'Pkp' => $data->pkp,
                'PungutPbjt' => $data->pungutPbjt,
                'BiayaLayanan' => ['Aktif' => $data->biayaLayananAktif, 'Persen' => $data->biayaLayananAktif ? $persen : '0.00'],
                'HargaTermasukPajak' => $data->hargaTermasukPajak,
            ];

            if ($baru === $lama) {
                return $outlet;
            }

            $outlet->ProfilPajak = $baru;
            $outlet->save();
            $this->audit->Catat('outlet.pajak.ubah', $outlet, nilaiLama: $lama, nilaiBaru: $baru);

            return $outlet;
        });
    }

    private static function AmbilPersen(string $persen): string
    {
        try {
            $nilai = BigDecimal::of(trim($persen) === '' ? '0' : trim($persen));
        } catch (MathException) {
            $nilai = null;
        }

        if ($nilai === null || $nilai->isNegative() || $nilai->isGreaterThan(DataProfilPajakOutlet::PERSEN_BIAYA_LAYANAN_MAKSIMAL) || $nilai->getScale() > 2) {
            throw new PelanggaranAturanBisnis('PersenBiayaLayananTidakSah', 'Service charge 0 sampai '.DataProfilPajakOutlet::PERSEN_BIAYA_LAYANAN_MAKSIMAL.' persen.', 'PersenBiayaLayanan');
        }

        return (string) $nilai->toScale(2, RoundingMode::Unnecessary);
    }
}
