<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Kontrak;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;

/**
 * Penangan satu jenis item outbox POS (F-06: `Shift.Buka`, `MutasiKas.Catat`; F-07 menambah penjualan, dst.).
 * Didaftarkan dengan tag `TAG`. `Proses()` idempoten per Uuid item: Uuid yang sudah diterima = `Duplikat`;
 * pelanggaran aturan bisnis dilempar sebagai `PelanggaranAturanBisnis` (menjadi `Ditolak`). Setiap item berjalan di
 * transaksinya sendiri.
 */
interface PenanganItemSinkron
{
    public const TAG = 'sinkron.penangan-item';

    /** Nilai `Jenis` item yang ditangani, misal `Shift.Buka`. */
    public function AmbilJenis(): string;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PelanggaranAturanBisnis
     */
    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron;
}
