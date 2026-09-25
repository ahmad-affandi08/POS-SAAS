<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembelian;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Aturan validasi & konversi bersama formulir pembelian (F-04 fase 1): uang string desimal ≤ 2 angka, jumlah ≤ 4
 * angka (tidak pernah float), tanggal `TTTT-BB-HH`, lampiran sesuai `config('akuntansi.*Lampiran*')`.
 */
final class AturanPembelian
{
    public const UANG = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    public const JUMLAH = 'regex:/^\d{1,14}(\.\d{1,4})?$/';

    /**
     * @return list<string>
     */
    public static function Lampiran(): array
    {
        return ['nullable', 'file', 'mimes:'.implode(',', (array) config('akuntansi.EkstensiLampiran')), 'max:'.(int) config('akuntansi.UkuranMaksimalLampiranKb')];
    }

    /**
     * @return array<string, string>
     */
    public static function Pesan(): array
    {
        return [
            'regex' => ':Attribute harus angka dengan pemisah desimal titik (uang maks. 2 desimal, jumlah maks. 4 desimal).',
        ];
    }

    public static function Uang(mixed $nilai, string $bawaan = '0'): Uang
    {
        return Uang::Dari(is_string($nilai) && $nilai !== '' ? $nilai : $bawaan);
    }

    public static function UangAtauNull(mixed $nilai): ?Uang
    {
        return is_string($nilai) && $nilai !== '' ? Uang::Dari($nilai) : null;
    }

    public static function Jumlah(mixed $nilai): Kuantitas
    {
        return Kuantitas::Dari(is_string($nilai) && $nilai !== '' ? $nilai : '0');
    }

    public static function Tanggal(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || $nilai === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $nilai) ?: null;
    }

    public static function Teks(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}
