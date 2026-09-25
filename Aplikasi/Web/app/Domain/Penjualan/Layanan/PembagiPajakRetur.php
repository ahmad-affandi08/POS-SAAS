<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pembagian pajak satu baris retur ke kode jenis pajak (F-14a laporan pajak), dengan aturan yang sama seperti jurnal
 * retur `TerimaReturPenjualanPos::BagiPajak` (J-09.2): bobot per kode = tarif × pengali DPP dari `SnapshotPajak` baris
 * penjualan, bagian terakhir mengambil sisa sehingga Σ bagian = pajak baris; baris tanpa snapshot memakai kode pajak
 * pertama dokumen (`kodeCadangan`).
 */
final class PembagiPajakRetur
{
    /**
     * @param  list<array<string, mixed>>|null  $snapshot  `PenjualanDetail.SnapshotPajak`
     * @return array<string, Uang> kode jenis pajak → bagian pajak retur
     */
    public static function Bagi(Uang $pajak, ?array $snapshot, string $kodeCadangan): array
    {
        if ($pajak->BernilaiNol()) {
            return [];
        }

        $snapshot = array_values(array_filter($snapshot ?? [], fn (mixed $p): bool => is_array($p) && isset($p['Kode'])));

        if ($snapshot === []) {
            return [$kodeCadangan => $pajak];
        }

        $bobot = array_map(fn (array $p): BigDecimal => BigDecimal::of(self::Teks($p['Tarif'] ?? '0'))
            ->multipliedBy(self::Teks($p['PengaliDppPembilang'] ?? '1'))
            ->dividedBy(self::Teks($p['PengaliDppPenyebut'] ?? '1'), 10, RoundingMode::HalfUp), $snapshot);
        $totalBobot = array_reduce($bobot, fn (BigDecimal $t, BigDecimal $b): BigDecimal => $t->plus($b), BigDecimal::zero());
        $sisa = $pajak;
        $hasil = [];

        foreach ($snapshot as $k => $p) {
            $bagian = $k === array_key_last($snapshot) || $totalBobot->isZero()
                ? $sisa
                : $pajak->Kali($bobot[$k]->dividedBy($totalBobot, 10, RoundingMode::HalfUp));
            $sisa = $sisa->Kurangi($bagian);
            $kode = self::Teks($p['Kode']);
            $hasil[$kode] = ($hasil[$kode] ?? Uang::Nol())->Tambah($bagian);

            if ($sisa->BernilaiNol()) {
                break;
            }
        }

        return $hasil;
    }

    private static function Teks(mixed $nilai): string
    {
        return is_string($nilai) || is_int($nilai) ? (string) $nilai : '0';
    }
}
