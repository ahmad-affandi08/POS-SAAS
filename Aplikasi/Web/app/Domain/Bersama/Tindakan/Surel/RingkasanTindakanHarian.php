<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Surel;

use Illuminate\Mail\Mailable;

/**
 * Ringkasan pagi Kotak Tindakan (D-23 D bagian 4): butir Penting & Perhatian yang boleh dilihat penerima, dengan
 * tautan langsung ke halaman penyelesaiannya. Berhenti berlangganan di halaman Kotak Tindakan.
 */
final class RingkasanTindakanHarian extends Mailable
{
    /**
     * @param  list<array{Tingkat: string, Judul: string, Jumlah: int, Keterangan: string, Tautan: string}>  $butir
     */
    public function __construct(
        public readonly string $nama,
        public readonly string $namaUsaha,
        public readonly string $tanggal,
        public readonly array $butir,
        public readonly string $tautanKotak,
    ) {
        $jumlah = count($butir);
        $this->subject("{$namaUsaha}: {$jumlah} hal perlu diperhatikan hari ini")
            ->text('Surel.Tenant.RingkasanTindakanHarian', [
                'Nama' => $nama,
                'NamaUsaha' => $namaUsaha,
                'Tanggal' => $tanggal,
                'Butir' => $butir,
                'TautanKotak' => $tautanKotak,
            ]);
    }
}
