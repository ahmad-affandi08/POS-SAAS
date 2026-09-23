<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Status simpan dokumen legal (P-06). Status tampilan Terjadwal/Berlaku/Digantikan dihitung dari tanggal (BR-P06.4).
 */
enum StatusDokumenLegal: string
{
    case Draf = 'Draf';
    case Terbit = 'Terbit';
}
