<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Akun COA tenant (PRD §11.2, §15.3, BR-01.2). Kode unik per tenant. `Sistem` = dibuat dari template sektor; akun
 * yang sudah ada (termasuk yang diganti namanya oleh tenant) tidak pernah diubah oleh penerapan template berikutnya.
 * F-13a: `Aktif` (akun terpakai hanya dinonaktifkan) dan `KasBank` (akun kas/bank untuk transaksi kas & bank).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Kode
 * @property string $Nama
 * @property TipeAkun $Jenis
 * @property int|null $IdInduk
 * @property bool $Sistem
 * @property int|null $IdOutlet
 * @property SaldoNormal $SaldoNormal
 * @property bool $Aktif
 * @property bool $KasBank
 */
final class Akun extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Akun';

    /** @var array<string, mixed> */
    protected $attributes = ['IdInduk' => null, 'Sistem' => false, 'IdOutlet' => null, 'Aktif' => true, 'KasBank' => false];

    /** Akun kontra (F-13a): saldo normalnya kebalikan saldo normal tipenya (misal Diskon penjualan, Prive). */
    public function CekKontra(): bool
    {
        return $this->SaldoNormal !== $this->Jenis->AmbilSaldoNormal();
    }

    /** "1-1100 Kas Outlet". */
    public function AmbilLabel(): string
    {
        return $this->Kode.' '.$this->Nama;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => TipeAkun::class, 'SaldoNormal' => SaldoNormal::class, 'Sistem' => 'boolean', 'Aktif' => 'boolean', 'KasBank' => 'boolean'];
    }
}
