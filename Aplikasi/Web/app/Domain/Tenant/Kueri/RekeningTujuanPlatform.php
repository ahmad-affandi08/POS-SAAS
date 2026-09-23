<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

/**
 * Rekening tujuan transfer tagihan langganan platform (P-08 Fase 0), dari `config/tagihan.php` (env), bukan kode.
 */
final class RekeningTujuanPlatform
{
    /**
     * @return list<array{Kode: string, NamaBank: string, NomorRekening: string, AtasNama: string}>
     */
    public function Ambil(): array
    {
        $daftar = config('tagihan.RekeningTujuan');
        $hasil = [];

        foreach (is_array($daftar) ? $daftar : [] as $baris) {
            if (! is_array($baris)) {
                continue;
            }

            $rekening = [
                'Kode' => self::Teks($baris, 'Kode'),
                'NamaBank' => self::Teks($baris, 'NamaBank'),
                'NomorRekening' => self::Teks($baris, 'NomorRekening'),
                'AtasNama' => self::Teks($baris, 'AtasNama'),
            ];

            if ($rekening['Kode'] !== '' && $rekening['NamaBank'] !== '' && $rekening['NomorRekening'] !== '') {
                $hasil[] = $rekening;
            }
        }

        return $hasil;
    }

    /**
     * @return array{Kode: string, NamaBank: string, NomorRekening: string, AtasNama: string}|null
     */
    public function Cari(string $kode): ?array
    {
        foreach ($this->Ambil() as $rekening) {
            if ($rekening['Kode'] === $kode) {
                return $rekening;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $baris
     */
    private static function Teks(array $baris, string $kunci): string
    {
        $nilai = $baris[$kunci] ?? '';

        return is_string($nilai) ? trim($nilai) : '';
    }
}
