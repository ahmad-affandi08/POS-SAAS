<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Cakupan aturan `FlagFitur` (P-10). Urutan evaluasi: `Global` bernilai mati = kill switch (mengalahkan semua aturan);
 * selain itu `Tenant` > `Paket` > `Persentase` > `Global` hidup.
 */
enum CakupanFlagFitur: string
{
    case Global = 'Global';
    case Paket = 'Paket';
    case Tenant = 'Tenant';
    case Persentase = 'Persentase';
}
