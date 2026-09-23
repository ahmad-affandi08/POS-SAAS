<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Surel;

use Illuminate\Mail\Mailable;

/**
 * Pemberitahuan ke pemilik akun bahwa email atau nomor WhatsApp-nya dipakai untuk mendaftar lagi (BR-00.1, §25 no. 18).
 * Pendaftar hanya melihat pesan umum; pemilik akun yang sebenarnya mendapat petunjuk lengkap di sini.
 */
final class UpayaPendaftaranAkunTerdaftar extends Mailable
{
    /**
     * @param  list<string>  $identitas  label identitas yang dipakai, misal "email" dan "nomor WhatsApp"
     */
    public function __construct(public readonly string $nama, public readonly array $identitas)
    {
        $this->subject('Ada upaya pendaftaran memakai data akun Anda')
            ->text('Surel.Tenant.UpayaPendaftaranAkunTerdaftar', [
                'Nama' => $nama,
                'Identitas' => implode(' dan ', $identitas),
                'TautanMasuk' => route('masuk'),
                'TautanLupa' => route('lupa-kata-sandi'),
            ]);
    }
}
