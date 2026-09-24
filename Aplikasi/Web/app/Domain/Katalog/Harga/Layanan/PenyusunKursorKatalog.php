<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Carbon\CarbonImmutable;
use JsonException;
use Throwable;

/**
 * Kursor sinkron katalog POS (F-03 D.3): base64url dari `{"W":"<waktu ISO µs UTC>","S":1}`. `W` = awal kueri dikurangi
 * tumpang tindih (`katalog.Pos.TumpangTindihDetik`), sehingga baris yang ditulis bersamaan tidak terlewat; POS
 * menyimpan per `Uuid` sehingga pengiriman ulang aman. Kursor tidak valid → `KursorTidakValid` (422).
 */
final class PenyusunKursorKatalog
{
    public const SKEMA = 1;

    public function Buat(CarbonImmutable $waktu): string
    {
        $isi = json_encode(['W' => $waktu->utc()->format('Y-m-d\TH:i:s.u\Z'), 'S' => self::SKEMA], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($isi), '+/', '-_'), '=');
    }

    public function Baca(string $kursor): CarbonImmutable
    {
        try {
            $mentah = base64_decode(strtr($kursor, '-_', '+/'), true);
            $isi = is_string($mentah) ? json_decode($mentah, true, 4, JSON_THROW_ON_ERROR) : null;

            if (! is_array($isi) || ($isi['S'] ?? null) !== self::SKEMA || ! is_string($isi['W'] ?? null)
                || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $isi['W']) !== 1) {
                throw self::Galat();
            }

            $waktu = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $isi['W'], 'UTC');
        } catch (JsonException) {
            throw self::Galat();
        } catch (PelanggaranAturanBisnis $galat) {
            throw $galat;
        } catch (Throwable) {
            throw self::Galat();
        }

        if (! $waktu instanceof CarbonImmutable || $waktu->greaterThan(CarbonImmutable::now()->addMinutes(5))) {
            throw self::Galat();
        }

        return $waktu;
    }

    private static function Galat(): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('KursorTidakValid', 'Kursor sinkron katalog tidak valid. Lakukan sinkron lengkap.', 'sejak', 422);
    }
}
