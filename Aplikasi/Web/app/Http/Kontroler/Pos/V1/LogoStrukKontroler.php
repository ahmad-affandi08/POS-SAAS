<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /api/pos/v1/logo-struk` (PRD v1.79): logo usaha tenant perangkat untuk dicetak di kepala struk (disimpan
 * offline di perangkat). Tanpa logo atau logo dimatikan di pengaturan struk = 404.
 */
final class LogoStrukKontroler extends Kontroler
{
    public function Unduh(Request $permintaan, ProfilTenant $profil, PengaturanStrukTenant $pengaturan, PenyimpanLogoTenant $penyimpan): StreamedResponse
    {
        $idTenant = AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdTenant;
        $path = $profil->Ambil($idTenant)['PathLogo'];
        abort_if($path === null || ! $pengaturan->Ambil($idTenant)->tampilkanLogo, 404);

        return $penyimpan->Unduh($path);
    }
}
