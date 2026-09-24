<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Enum;

/**
 * Hasil per item outbox `POST /api/pos/v1/sinkron/kirim` (PRD §16.3, §18): Diterima (baru disimpan), Duplikat
 * (Uuid sudah pernah diterima; aman dihapus dari outbox), Ditolak (pelanggaran aturan bisnis; perangkat
 * memindahkannya ke daftar "Perlu Tindakan").
 */
enum StatusItemSinkron: string
{
    case Diterima = 'Diterima';
    case Duplikat = 'Duplikat';
    case Ditolak = 'Ditolak';
}
