<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Enum;

/**
 * Jenis tagihan langganan. `Aktivasi`: mulai berlangganan berbayar (dari Trial, Gratis, Ditangguhkan, atau ganti
 * paket sebelum aktif), periode dimulai saat pembayaran diterima. `Perpanjangan`: paket yang sama saat langganan
 * Aktif/Tertunggak, periode baru menyambung dari akhir periode berjalan.
 */
enum JenisTagihanLangganan: string
{
    case Aktivasi = 'Aktivasi';
    case Perpanjangan = 'Perpanjangan';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Aktivasi => 'Aktivasi langganan',
            self::Perpanjangan => 'Perpanjangan langganan',
        };
    }
}
