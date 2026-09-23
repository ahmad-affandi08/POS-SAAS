<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use Illuminate\Support\Str;

/**
 * Menghapus nilai kredensial dari pesan galat pihak ketiga sebelum disimpan atau ditampilkan (BR-P05.6).
 */
final class PenyaringPesan
{
    public const PANJANG_MAKSIMAL = 300;

    /**
     * @param  array<string, string>  $kredensial
     */
    public static function Saring(string $pesan, array $kredensial): string
    {
        $rahasia = array_filter(array_values($kredensial), fn (string $nilai) => $nilai !== '');

        return Str::limit(str_replace($rahasia, '[disembunyikan]', $pesan), self::PANJANG_MAKSIMAL);
    }
}
