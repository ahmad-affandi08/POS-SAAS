<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Enum;

/**
 * Status faktur pembelian (F-04 fase 1) menurut sisa hutang (Total − JumlahDibayar − JumlahRetur): BelumDibayar,
 * DibayarSebagian, Lunas; pembayaran & retur memindahkan status di antara tiga status terbuka. Dibatalkan (jurnal
 * pembalik) final; syaratnya (belum dibayar & belum diretur, atau pembatalan belanja stok utuh) dijaga Aksi.
 */
enum StatusFakturPembelian: string
{
    case BelumDibayar = 'BelumDibayar';
    case DibayarSebagian = 'DibayarSebagian';
    case Lunas = 'Lunas';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return $this !== self::Dibatalkan && $tujuan !== $this;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::BelumDibayar => 'Belum dibayar',
            self::DibayarSebagian => 'Dibayar sebagian',
            self::Lunas => 'Lunas',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
