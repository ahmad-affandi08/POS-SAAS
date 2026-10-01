<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Surel;

use App\Domain\Bersama\Surel\SurelDasar;
use Symfony\Component\Mime\Email;

/**
 * Email kampanye promosi ke pelanggan (CRM-07); isi sama dengan teks WhatsApp. Membawa header `List-Unsubscribe`
 * (RFC 2369) ke tautan berhenti berlangganan supaya klien email menampilkan tombol berhenti.
 */
final class PesanKampanyePelanggan extends SurelDasar
{
    public function __construct(
        public readonly string $namaUsaha,
        public readonly string $judul,
        public readonly string $isi,
        public readonly string $tautanBerhenti,
    ) {
        $this->subject(mb_substr($judul, 0, 150))
            ->IsiSurel('Tenant.PesanKampanyePelanggan', ['Isi' => $isi, 'NamaUsaha' => $namaUsaha, 'TautanBerhenti' => $tautanBerhenti]);
        $this->withSymfonyMessage(function (Email $pesan) use ($tautanBerhenti): void {
            $pesan->getHeaders()->addTextHeader('List-Unsubscribe', "<{$tautanBerhenti}>");
        });
    }
}
