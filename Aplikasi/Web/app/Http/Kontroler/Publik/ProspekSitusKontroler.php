<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Situs\Aksi\TerimaProspekSitus;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Publik\KirimProspekSitusPermintaan;
use Illuminate\Http\RedirectResponse;

/**
 * Situs pemasaran bagian B (§13.9): formulir kontak/minta demo di blok `FormulirProspek`.
 */
final class ProspekSitusKontroler extends Kontroler
{
    public function Kirim(KirimProspekSitusPermintaan $permintaan, TerimaProspekSitus $terima): RedirectResponse
    {
        if (! $permintaan->CekBot()) {
            $terima->Jalankan($permintaan->AmbilIsian(), (string) $permintaan->ip());
        }

        return back()->with('ProspekTerkirim', true);
    }
}
