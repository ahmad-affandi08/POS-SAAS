<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\PanduanAwal\Kueri\LangkahBerikutnya;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda back-office. F-01 menambahkan checklist "Langkah Berikutnya" (kosong = bagian disembunyikan); dasbor
 * dibangun F-14. Wizard panduan awal ada di `PanduanAwalKontroler`.
 */
final class BerandaKelolaKontroler extends DasarKelolaKontroler
{
    public function Beranda(LangkahBerikutnya $langkahBerikutnya): Response
    {
        return Inertia::render('Kelola/Beranda', [
            'LangkahBerikutnya' => $langkahBerikutnya->Ambil($this->IdTenant(), $this->Pelaku()->Id),
        ]);
    }
}
