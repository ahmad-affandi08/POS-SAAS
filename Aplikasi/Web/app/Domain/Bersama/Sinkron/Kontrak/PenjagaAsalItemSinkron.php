<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Kontrak;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;

/**
 * Menentukan perangkat asal tiap item outbox (audit P0 F-01, BR-02.3). Item dikreditkan ke perangkat yang
 * membuatnya, bukan ke token yang kebetulan mengirim: perangkat dicabut hanya boleh mengirim item yang dibuat sebelum
 * `DicabutPada`, dan perangkat yang diaktifkan ulang mengirim item lama atas nama perangkat asalnya.
 */
interface PenjagaAsalItemSinkron
{
    /**
     * @throws PelanggaranAturanBisnis item tidak boleh diterima (asal tidak dikenal, dibuat setelah dicabut)
     */
    public function Tentukan(string $uuidItem, ?string $uuidPerangkatAsal, DataKonteksSinkron $pengirim): DataKonteksSinkron;

    /** Tandai item jalur pemulihan yang diterima (atau sudah ada) untuk ditinjau. Idempoten per Uuid item. */
    public function CatatPemulihan(string $uuidItem, string $jenis, DataKonteksSinkron $asal, DataKonteksSinkron $pengirim): void;
}
