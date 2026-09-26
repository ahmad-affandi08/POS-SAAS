<?php

declare(strict_types=1);

/*
 * Pelanggan (F-16). Kunci PascalCase (D-05). Nilai uang sebagai string Rupiah (tidak pernah float).
 */
return [
    // F-16d bagian 1: batas isi saldo deposit per transaksi di POS (Rupiah bulat) dan batas satu penyesuaian manual.
    'Deposit' => [
        'MinimalIsi' => '1000',
        'MaksimalIsi' => '10000000',
        'MaksimalPenyesuaian' => '10000000',
    ],
];
