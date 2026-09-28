<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Penjaga aturan #8 di tingkat model dokumen grosir: dokumen tidak pernah dihapus, dan setelah dikonfirmasi/diposting
 * hanya kolom yang disebut `AmbilKolomBolehBerubah()` yang boleh berubah. Null = semua kolom boleh berubah (draf).
 *
 * Kembaran `Pembelian\Model\JagaDokumenPembelian`; sengaja tidak diangkat ke `Domain/Bersama` supaya perubahan ini
 * tidak menyentuh enam model pembelian yang sudah berjalan. Bila domain ketiga membutuhkannya, keduanya disatukan.
 */
trait JagaDokumenGrosir
{
    public static function bootJagaDokumenGrosir(): void
    {
        static::updating(function (Model $dokumen): void {
            /** @var self $dokumen */
            $boleh = $dokumen->AmbilKolomBolehBerubah();

            if ($boleh === null) {
                return;
            }

            $terlarang = array_diff(array_keys($dokumen->getDirty()), [...$boleh, 'DiubahPada']);

            if ($terlarang !== []) {
                throw new LogicException('Dokumen '.$dokumen->getTable().' yang sudah dikonfirmasi tidak bisa diubah: '.implode(', ', $terlarang).'. Koreksi lewat dokumen pembalik.');
            }
        });

        static::deleting(function (Model $dokumen): void {
            throw new LogicException('Dokumen '.$dokumen->getTable().' tidak pernah dihapus; koreksi lewat pembatalan (dokumen pembalik).');
        });
    }

    /**
     * @return list<string>|null
     */
    abstract public function AmbilKolomBolehBerubah(): ?array;
}
