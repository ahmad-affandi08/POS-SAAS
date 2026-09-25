<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Penjualan\Kueri\StrukDigitalPenjualan;
use App\Domain\Penjualan\Layanan\KodeStrukDigital;
use App\Domain\Tenant\Kueri\ProfilTenant;
use App\Http\Kontroler\Kontroler;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Struk digital publik `/s/{kodeStruk}` (POS-11), tanpa login. Tenant diambil dari kode lalu dipasang sebagai konteks
 * (seperti device token) sehingga pencarian penjualan tetap lewat scope `MilikTenant`. Struk yang belum tersinkron,
 * dimatikan, atau tidak dikenal menampilkan halaman "belum tersedia" (404).
 */
final class StrukDigitalKontroler extends Kontroler
{
    public function Tampilkan(string $kodeStruk, KonteksTenant $konteks, ProfilTenant $profil, StrukDigitalPenjualan $kueri): SymfonyResponse
    {
        $kode = KodeStrukDigital::Urai($kodeStruk);
        $struk = null;

        if ($kode !== null && $profil->CekAda($kode['IdTenant'])) {
            $konteks->Atur($kode['IdTenant']);

            try {
                $struk = $kueri->Ambil($kode['IdTenant'], $kode['Uuid']);
            } finally {
                $konteks->Kosongkan();
            }
        }

        return Inertia::render('Publik/StrukDigital', ['Struk' => $struk])
            ->toResponse(request())
            ->setStatusCode($struk === null ? 404 : 200);
    }
}
