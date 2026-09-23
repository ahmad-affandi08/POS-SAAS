<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola;

use App\Http\Kontroler\Kontroler;
use Inertia\Inertia;
use Inertia\Response;

final class BerandaKontroler extends Kontroler
{
    public function Tampilkan(): Response
    {
        return Inertia::render('Pengelola/Beranda');
    }
}
