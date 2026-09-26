<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/**
 * Deposit pelanggan tenant aktif (F-16d bagian 1): berlaku bila paket punya fitur `pelanggan.deposit` (Pro ke atas).
 * Batas isi per transaksi dari `config/pelanggan.php` (`Deposit.MinimalIsi`/`MaksimalIsi`, Rupiah bulat).
 */
final class PengaturanDepositTenant
{
    public const KUNCI_FITUR = 'pelanggan.deposit';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    public function CekBerlaku(): bool
    {
        return $this->fitur->CekAktif($this->konteks->Wajib(), self::KUNCI_FITUR);
    }

    public static function AmbilMinimalIsi(): Uang
    {
        return Uang::Dari((string) config('pelanggan.Deposit.MinimalIsi', '1000'));
    }

    public static function AmbilMaksimalIsi(): Uang
    {
        return Uang::Dari((string) config('pelanggan.Deposit.MaksimalIsi', '10000000'));
    }

    public static function AmbilMaksimalPenyesuaian(): Uang
    {
        return Uang::Dari((string) config('pelanggan.Deposit.MaksimalPenyesuaian', '10000000'));
    }

    /**
     * Bentuk data awal POS & halaman back-office.
     *
     * @return array{Berlaku: bool, MinimalIsi: string, MaksimalIsi: string}
     */
    public function KeLarik(): array
    {
        return [
            'Berlaku' => $this->CekBerlaku(),
            'MinimalIsi' => self::AmbilMinimalIsi()->KeString(),
            'MaksimalIsi' => self::AmbilMaksimalIsi()->KeString(),
        ];
    }
}
