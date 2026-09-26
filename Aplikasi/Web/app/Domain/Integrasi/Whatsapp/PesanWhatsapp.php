<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp;

/**
 * Pesan WhatsApp keluar. [teks] dipakai penyedia tidak resmi dan WhatsApp Cloud API di dalam jendela 24 jam; di luar
 * jendela itu API resmi mewajibkan templat yang disetujui Meta ([namaTemplat] + [parameterTemplat] untuk isi {{1}},
 * {{2}}, ...).
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
}
