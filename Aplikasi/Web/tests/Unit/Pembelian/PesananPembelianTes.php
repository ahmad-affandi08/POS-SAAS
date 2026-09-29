<?php

declare(strict_types=1);

use App\Domain\Pembelian\Model\PesananPembelian;

it('menentukan tanggal penerimaan bawaan satu hari sebelum tanggal PO', function (): void {
    $po = new PesananPembelian;
    $po->Tanggal = '2026-09-30';

    expect($po->AmbilTanggalPenerimaanBawaan()->toDateString())->toBe('2026-09-29');

    $po->Tanggal = '2026-03-01';

    expect($po->AmbilTanggalPenerimaanBawaan()->toDateString())->toBe('2026-02-28');
});
