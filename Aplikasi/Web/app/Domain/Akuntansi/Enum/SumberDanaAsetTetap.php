<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/**
 * Asal pencatatan aset tetap (FIN-10): `KasBank` = dibeli sekarang, dibayar dari akun kas/bank; `SaldoAwal` = harta
 * yang sudah dimiliki sebelum memakai aplikasi (lawan jurnal Ekuitas Saldo Awal, akumulasi penyusutan yang sudah ada
 * dicatat sebagai `AkumulasiAwal`).
 */
enum SumberDanaAsetTetap: string
{
    case KasBank = 'KasBank';
    case SaldoAwal = 'SaldoAwal';
}
