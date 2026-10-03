<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp;

/**
 * Pesan WhatsApp keluar. [teks] dipakai penyedia tidak resmi dan WhatsApp Cloud API di dalam jendela 24 jam; di luar
 * jendela itu API resmi mewajibkan templat yang disetujui Meta ([namaTemplat] + [parameterTemplat] untuk isi {{1}},
 * {{2}}, ...). [kodeSalin] (F-17 bagian 3) mengisi tombol "salin kode" templat autentikasi Meta; penyedia lain
 * mengabaikannya karena kodenya sudah ada di [teks].
 */
final readonly class PesanWhatsapp
{
    /**
     * @param  list<string>  $parameterTemplat
     */
    public function __construct(
        public string $nomor,
        public string $teks,
        public ?string $namaTemplat = null,
        public array $parameterTemplat = [],
        public ?string $kodeSalin = null,
    ) {}

    /** Nomor Indonesia ke format internasional tanpa tanda plus: 0812… / +62812… / 62812… → 62812…. */
    public static function RapikanNomor(string $nomor): string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($angka, '0')) {
            return '62'.substr($angka, 1);
        }

        if (str_starts_with($angka, '8')) {
            return '62'.$angka;
        }

        return $angka;
    }

    /** Nomor HP Indonesia yang sah untuk WhatsApp (`628…`, 10–15 digit); null bila kosong atau tidak sah. */
    public static function AmbilNomorSah(?string $nomor): ?string
    {
        if ($nomor === null || trim($nomor) === '') {
            return null;
        }

        $rapi = self::RapikanNomor($nomor);

        return preg_match('/^628\d{7,12}$/', $rapi) === 1 ? $rapi : null;
    }
}
