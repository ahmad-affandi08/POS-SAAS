<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Billing;

/**
 * Transaksi Snap yang sudah dibuat di Midtrans. `token` dipakai popup Snap.js di halaman tagihan; `urlRedirect`
 * adalah cadangan bila popup diblokir peramban.
 */
final class HasilSnap
{
    public function __construct(
        public readonly string $token,
        public readonly string $urlRedirect,
    ) {}
}
