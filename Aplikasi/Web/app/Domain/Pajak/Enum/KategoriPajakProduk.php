<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Enum;

/**
 * Kategori pajak produk (PRD §8 F-03, §12.2) di `KelompokPajak.Kategori`. `Lainnya` = pajak lain/daerah di luar PPN &
 * PBJT (DesainF03 H.16). Aturan isi detail per kategori dijaga `SimpanKelompokPajak`; tarif tidak pernah disimpan di
 * sini (dicari dari `TarifPajak` bertanggal saat transaksi).
 */
enum KategoriPajakProduk: string
{
    case KenaPpn = 'KenaPpn';
    case BebasPpn = 'BebasPpn';
    case KenaPbjt = 'KenaPbjt';
    case NonPajak = 'NonPajak';
    case Lainnya = 'Lainnya';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::KenaPpn => 'Kena PPN',
            self::BebasPpn => 'Bebas PPN',
            self::KenaPbjt => 'Kena PB1 (PBJT)',
            self::NonPajak => 'Non-pajak',
            self::Lainnya => 'Pajak lain',
        };
    }

    /** Kategori tanpa detail pajak sama sekali. */
    public function CekTanpaPajak(): bool
    {
        return $this === self::BebasPpn || $this === self::NonPajak;
    }

    /**
     * Kategori dari kode jenis pajak detail kelompok (aturan sama dengan migrasi 000124): ada `Ppn` → KenaPpn; selain
     * itu ada `PbjtMakananMinuman` → KenaPbjt; tanpa detail → NonPajak; sisanya → Lainnya.
     *
     * @param  array<int, string>  $kodeJenisPajak
     */
    public static function TentukanDariKodeJenisPajak(array $kodeJenisPajak): self
    {
        return match (true) {
            in_array('Ppn', $kodeJenisPajak, true) => self::KenaPpn,
            in_array('PbjtMakananMinuman', $kodeJenisPajak, true) => self::KenaPbjt,
            $kodeJenisPajak === [] => self::NonPajak,
            default => self::Lainnya,
        };
    }
}
