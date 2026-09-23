<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Model\Langganan;

/**
 * Kode paket langganan tenant saat ini, untuk domain lain yang perlu membedakan layanan per paket
 * (misal SLA tiket dukungan P-09) tanpa membaca tabel langganan langsung.
 */
final class PaketTenant
{
    public function AmbilKode(int $idTenant): ?string
    {
        $langganan = Langganan::query()->with('Paket:Id,Kode')->where('IdTenant', $idTenant)->first(['Id', 'IdPaket']);

        return $langganan?->Paket->Kode;
    }
}
