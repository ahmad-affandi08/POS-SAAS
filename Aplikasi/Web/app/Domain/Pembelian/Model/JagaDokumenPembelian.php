<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Penjaga aturan #8 di tingkat model dokumen pembelian: dokumen tidak pernah dihapus, dan setelah diposting/diajukan
 * hanya kolom yang disebut `AmbilKolomBolehBerubah()` (perpindahan status, pembatalan, saldo terpakai) yang boleh
 * berubah. Null = semua kolom boleh berubah (draf PO).
 */
trait JagaDokumenPembelian
{
    public static function bootJagaDokumenPembelian(): void
    {
        static::updating(function (Model $dokumen): void {
            /** @var self $dokumen */
            $boleh = $dokumen->AmbilKolomBolehBerubah();

            if ($boleh === null) {
                return;
            }

            $terlarang = array_diff(array_keys($dokumen->getDirty()), [...$boleh, 'DiubahPada']);

            if ($terlarang !== []) {
                throw new LogicException('Dokumen '.$dokumen->getTable().' yang sudah diposting tidak bisa diubah: '.implode(', ', $terlarang).'. Koreksi lewat dokumen pembalik.');
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
