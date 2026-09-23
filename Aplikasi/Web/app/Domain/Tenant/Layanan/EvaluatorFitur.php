<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Tenant\Data\SumberFitur;

/**
 * Evaluasi fitur & batas tenant (P-04, §8 P-04 "Evaluasi fitur untuk tenant", BR-P04.3, BR-P04.7):
 *
 *     FiturAktif = (fitur di paket ATAU add-on aktif ATAU override pengelola aktif)
 *                  DAN flag fitur global mengizinkan (P-10)
 *                  DAN template/outlet mengaktifkan modul tersebut
 *
 * Batas efektif = batas paket + tambahan add-on × jumlah, lalu ditimpa override pengelola. Null = tak terbatas
 * dan tetap tak terbatas. Server selalu penentu; aplikasi hanya memakai hasil ini untuk UX.
 */
final class EvaluatorFitur
{
    public function CekFiturAktif(SumberFitur $sumber, string $kunci): bool
    {
        $dimiliki = in_array($kunci, $sumber->fiturPaket, true)
            || in_array($kunci, $sumber->overrideFitur, true)
            || array_filter($sumber->addon, fn (array $addon) => $addon['KunciFitur'] === $kunci && $addon['Jumlah'] > 0) !== [];

        if (! $dimiliki || ($sumber->flagFitur[$kunci] ?? true) === false) {
            return false;
        }

        return $sumber->modulOutletAktif === null || in_array($kunci, $sumber->modulOutletAktif, true);
    }

    /**
     * @return array<string, int|null>
     */
    public function HitungBatasEfektif(SumberFitur $sumber): array
    {
        $batas = $sumber->batasPaket;

        foreach ($sumber->addon as $addon) {
            foreach ($addon['TambahanBatas'] ?? [] as $kolom => $tambahan) {
                if (array_key_exists($kolom, $batas) && $batas[$kolom] !== null) {
                    $batas[$kolom] += $tambahan * max(0, $addon['Jumlah']);
                }
            }
        }

        foreach ($sumber->overrideBatas as $kolom => $nilai) {
            $batas[$kolom] = $nilai;
        }

        return $batas;
    }

    /** True bila pemakaian saat ini masih boleh bertambah satu lagi (dipakai PastikanBatasPaket di F-00/F-02). */
    public function CekMasihDalamBatas(SumberFitur $sumber, string $kolomBatas, int $pemakaianSaatIni): bool
    {
        $batas = $this->HitungBatasEfektif($sumber)[$kolomBatas] ?? null;

        return $batas === null || $pemakaianSaatIni < $batas;
    }
}
