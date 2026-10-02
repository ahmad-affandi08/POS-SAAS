<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

/**
 * Asal pesanan grosir (§9.7): dibuat di back-office, atau diambil salesman di lapangan lewat aplikasi (outbox
 * `PesananGrosir.Buat`, Modul Salesman bagian 1). Keduanya masuk sebagai Draf dan dikonfirmasi di back-office.
 */
enum SumberPesananGrosir: string
{
    case BackOffice = 'BackOffice';
    case Salesman = 'Salesman';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BackOffice => 'Back-office',
            self::Salesman => 'Salesman',
        };
    }
}
