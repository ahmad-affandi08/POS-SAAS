<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Enum;

/**
 * Pengirim pesan tiket: pengguna tenant, anggota tim internal, atau sistem (perubahan status/penugasan).
 */
enum JenisPengirimPesan: string
{
    case Pengguna = 'Pengguna';
    case Pengelola = 'Pengelola';
    case Sistem = 'Sistem';
}
