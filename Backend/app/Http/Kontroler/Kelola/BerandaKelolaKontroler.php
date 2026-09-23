<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Http\Kontroler\Kontroler;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda back-office & langkah awal setelah daftar. Isi sebenarnya dibangun F-01 (wizard) dan F-14 (dasbor).
 */
final class BerandaKelolaKontroler extends Kontroler
{
    public function Beranda(): Response
    {
        return Inertia::render('Kelola/Beranda');
    }

    public function PanduanAwal(): Response
    {
        return Inertia::render('Kelola/PanduanAwal');
    }
}
