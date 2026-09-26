<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

use RuntimeException;

/**
 * Gerbang menolak permintaan atau tidak bisa dihubungi. Pesan aman ditampilkan (tanpa kredensial).
 */
final class GalatGerbang extends RuntimeException {}
