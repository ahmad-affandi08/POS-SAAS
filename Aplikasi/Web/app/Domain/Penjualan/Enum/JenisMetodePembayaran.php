<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Enum;

use App\Domain\Referensi\Enum\JenisReferensiBank;

/**
 * Jenis metode pembayaran (PRD §15.3 `MetodePembayaran.Jenis`, F-08). Panduan awal (F-01 langkah 5) hanya membuat
 * QRIS statis, EDC, dan transfer; Tunai dibuat sistem dan selalu tersedia.
 */
enum JenisMetodePembayaran: string
{
    case Tunai = 'Tunai';
    case QrisStatis = 'QrisStatis';
    case QrisDinamis = 'QrisDinamis';
    case Edc = 'Edc';
    case Transfer = 'Transfer';
    case Ewallet = 'Ewallet';
    case Tempo = 'Tempo';
    case Deposit = 'Deposit';
    case Poin = 'Poin';
    case Voucher = 'Voucher';
    case Marketplace = 'Marketplace';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::QrisStatis => 'QRIS statis',
            self::QrisDinamis => 'QRIS dinamis',
            self::Edc => 'Kartu (EDC)',
            self::Transfer => 'Transfer bank',
            self::Ewallet => 'Dompet digital',
            self::Tempo => 'Tempo (piutang)',
            self::Deposit => 'Deposit pelanggan',
            self::Poin => 'Poin',
            self::Voucher => 'Voucher',
            self::Marketplace => 'Marketplace',
        };
    }

    public function CekBisaDibuatPanduan(): bool
    {
        return in_array($this, [self::QrisStatis, self::Edc, self::Transfer], true);
    }

    /**
     * Jenis referensi bank yang boleh dipilih (EDC: bank/jaringan EDC; transfer: bank/dompet digital).
     *
     * @return list<JenisReferensiBank>
     */
    public function AmbilJenisBankBoleh(): array
    {
        return match ($this) {
            self::Edc => [JenisReferensiBank::Bank, JenisReferensiBank::JaringanEdc],
            self::Transfer => [JenisReferensiBank::Bank, JenisReferensiBank::Ewallet],
            default => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function AmbilJenisPanduan(): array
    {
        return array_values(array_filter(self::cases(), fn (self $jenis) => $jenis->CekBisaDibuatPanduan()));
    }
}
