<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Versi optimistis draf dokumen persediaan: `versiDiubahPada` (ISO dari halaman) harus sama dengan `DiubahPada`
 * tersimpan; berbeda/kosong = `DokumenBerubah` (diubah orang lain sejak dibuka).
 */
final class PenjagaVersiDokumen
{
    public static function Pastikan(?string $versi, ?CarbonInterface $diubahPada): void
    {
        $sama = false;

        if ($versi !== null && $versi !== '' && $diubahPada !== null) {
            try {
                $sama = CarbonImmutable::parse($versi)->getTimestamp() === $diubahPada->getTimestamp();
            } catch (Throwable) {
                $sama = false;
            }
        }

        if (! $sama) {
            throw new PelanggaranAturanBisnis('DokumenBerubah', 'Draf ini baru saja diubah orang lain. Muat ulang halaman, lalu ulangi perubahan Anda.');
        }
    }
}
