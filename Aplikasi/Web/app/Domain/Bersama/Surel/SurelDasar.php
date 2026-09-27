<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Surel;

use Illuminate\Mail\Mailable;

/**
 * Induk semua email PAYOU (D-26).
 *
 * Setiap email dikirim **dua bagian**: HTML bermerek (`Surel.Html.{Grup}.{Nama}`) untuk klien biasa, dan teks
 * biasa (`Surel.{Grup}.{Nama}`) sebagai cadangan untuk klien yang memblokir HTML. Bagian teks juga menjaga
 * reputasi pengiriman: email HTML tanpa pasangan teks lebih sering dinilai spam.
 *
 * Keduanya dipasang lewat satu method supaya tidak bisa terpisah. Sebelumnya tiap mailable memanggil
 * `->text()` sendiri, dan itulah celah yang membuat badan email tidak pernah diuji.
 */
abstract class SurelDasar extends Mailable
{
    /**
     * Pasang badan HTML dan teks sekaligus dari satu nama templat.
     *
     * @param  string  $templat  Nama templat tanpa awalan, misal `Tenant.VerifikasiEmail`.
     * @param  array<string, mixed>  $data
     */
    protected function IsiSurel(string $templat, array $data = []): static
    {
        return $this->view('Surel.Html.'.$templat, $data)
            ->text('Surel.'.$templat, $data);
    }
}
