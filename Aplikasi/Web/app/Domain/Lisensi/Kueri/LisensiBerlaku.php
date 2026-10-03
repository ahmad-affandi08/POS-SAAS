<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Kueri;

use App\Domain\Lisensi\Data\DataLisensi;
use App\Domain\Lisensi\Galat\LisensiTidakSah;
use App\Domain\Lisensi\Layanan\PenandaLisensi;
use App\Domain\Lisensi\Model\LisensiTerpasang;

/**
 * Lisensi yang berlaku di server ini (D-35): baris `LisensiTerpasang` terbaru yang tanda tangannya masih sah. Hasil
 * diingat per proses sampai `Lupakan()` (dipanggil setelah pasang/ganti lisensi dan di test), karena dibaca di setiap
 * permintaan oleh `WajibLisensiSah` dan `SumberFiturTenant`.
 */
final class LisensiBerlaku
{
    private static ?DataLisensi $ingatan = null;

    private static bool $sudahDibaca = false;

    public function __construct(private readonly PenandaLisensi $penanda) {}

    /** Null bila belum ada lisensi terpasang atau berkas tersimpannya tidak lolos verifikasi. */
    public function Ambil(): ?DataLisensi
    {
        if (self::$sudahDibaca) {
            return self::$ingatan;
        }

        $baris = LisensiTerpasang::query()->orderByDesc('Id')->first();

        try {
            self::$ingatan = $baris === null ? null : $this->penanda->Baca($baris->IsiBerkas);
        } catch (LisensiTidakSah) {
            self::$ingatan = null;
        }

        self::$sudahDibaca = true;

        return self::$ingatan;
    }

    public static function Lupakan(): void
    {
        self::$ingatan = null;
        self::$sudahDibaca = false;
    }
}
