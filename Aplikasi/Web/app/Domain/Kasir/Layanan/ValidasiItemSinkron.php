<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

/**
 * Validasi bentuk data item outbox POS F-06 (sebelum masuk Aksi). Galat pertama menjadi `DataTidakValid` dengan
 * bidangnya, supaya perangkat bisa menampilkan alasan di daftar "Perlu Tindakan".
 */
final class ValidasiItemSinkron
{
    /** Uang: angka non-negatif, maks. 16 digit bulat & 2 desimal, titik sebagai pemisah desimal. */
    public const POLA_UANG = '/^\d{1,16}(\.\d{1,2})?$/';

    /** Waktu ISO-8601 dengan zona waktu eksplisit (Z atau ±hh:mm). */
    public const POLA_WAKTU = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $aturan
     * @return array<string, mixed>
     */
    public static function Validasi(array $data, array $aturan): array
    {
        $validator = Validator::make($data, $aturan);

        if ($validator->fails()) {
            $bidang = (string) array_key_first($validator->errors()->messages());

            throw new PelanggaranAturanBisnis('DataTidakValid', (string) $validator->errors()->first(), $bidang, 422, [
                'Galat' => $validator->errors()->messages(),
            ]);
        }

        return $validator->validated();
    }

    public static function AmbilWaktu(string $nilai): CarbonImmutable
    {
        return CarbonImmutable::parse($nilai)->utc();
    }
}
