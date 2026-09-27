<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Idempotensi\Layanan;

use App\Domain\Bersama\Idempotensi\Model\KunciIdempotensi;

/**
 * Penyimpanan respons per `Idempotency-Key` perangkat POS (audit F-12) di tenant aktif. Baris lewat masa simpan
 * dipangkas sedikit demi sedikit per perangkat saat menyimpan (tanpa kueri lintas tenant).
 */
final class PenyimpanIdempotensi
{
    public const JAM_SIMPAN = 24;

    private const PANGKAS_PER_SIMPAN = 50;

    public function Ambil(int $idPerangkat, string $kunci): ?KunciIdempotensi
    {
        return KunciIdempotensi::query()
            ->where('IdPerangkat', $idPerangkat)
            ->where('Kunci', $kunci)
            ->where('KedaluwarsaPada', '>', now())
            ->first();
    }

    public function Simpan(int $idTenant, int $idPerangkat, string $kunci, string $metode, string $jalur, string $hash, int $status, string $isi): void
    {
        KunciIdempotensi::query()->where('IdPerangkat', $idPerangkat)->where('KedaluwarsaPada', '<=', now())
            ->orderBy('Id')->limit(self::PANGKAS_PER_SIMPAN)->delete();

        $sekarang = now();
        KunciIdempotensi::query()->upsert([[
            'IdTenant' => $idTenant,
            'IdPerangkat' => $idPerangkat,
            'Kunci' => $kunci,
            'Metode' => $metode,
            'Jalur' => mb_substr($jalur, 0, 255),
            'HashPermintaan' => $hash,
            'StatusHttp' => $status,
            'Respons' => $isi,
            'KedaluwarsaPada' => $sekarang->copy()->addHours(self::JAM_SIMPAN),
            'DibuatPada' => $sekarang,
            'DiubahPada' => $sekarang,
        ]], ['IdTenant', 'IdPerangkat', 'Kunci'], ['Metode', 'Jalur', 'HashPermintaan', 'StatusHttp', 'Respons', 'KedaluwarsaPada', 'DiubahPada']);
    }
}
