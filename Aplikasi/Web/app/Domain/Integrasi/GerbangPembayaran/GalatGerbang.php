<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

use RuntimeException;

/**
 * Gerbang menolak permintaan atau tidak bisa dihubungi. Pesan aman ditampilkan (tanpa kredensial).
 *
 * Audit P0 F-02: `tidakPasti` = gerbang mungkin sudah memproses permintaan (koneksi putus/waktu habis, 5xx, 408/409/
 * 425/429, atau respons sukses yang tidak bisa dibaca). Hanya galat `tidakPasti` = false yang boleh dianggap "tidak
 * ada yang dibuat di gerbang".
 */
final class GalatGerbang extends RuntimeException
{
    public function __construct(string $pesan, public readonly bool $tidakPasti = false)
    {
        parent::__construct($pesan);
    }
}
