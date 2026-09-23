<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Data\SumberFitur;
use App\Domain\Tenant\Model\Langganan;

/**
 * Merakit masukan `EvaluatorFitur` dari langganan tenant (P-04 "Evaluasi fitur untuk tenant"). Tanpa langganan =
 * null (tidak ada fitur).
 *
 * TODO F-19/P-07/P-10: add-on aktif, override pengelola, dan flag fitur ikut dirakit setelah tabelnya tersedia.
 */
final class SumberFiturTenant
{
    public function Ambil(int $idTenant): ?SumberFitur
    {
        $langganan = Langganan::query()->with('Paket.Fitur')->where('IdTenant', $idTenant)->first();

        if ($langganan === null) {
            return null;
        }

        return new SumberFitur(
            fiturPaket: $langganan->Paket->AmbilKunciFitur(),
            batasPaket: $langganan->Paket->AmbilBatas(),
        );
    }
}
