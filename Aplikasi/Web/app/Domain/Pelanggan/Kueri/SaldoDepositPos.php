<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;

/**
 * Saldo deposit terkini untuk kasir sebelum membayar dengan deposit (F-16d bagian 1, wajib online). Pelanggan dicari
 * dari Uuid atau alias Uuid perangkat; null bila tidak ada, milik tenant lain, atau diarsipkan. `Berlaku` = paket
 * punya fitur `pelanggan.deposit`.
 */
final class SaldoDepositPos
{
    public function __construct(
        private readonly IdentitasPelanggan $identitas,
        private readonly PengaturanDepositTenant $pengaturan,
    ) {}

    /**
     * @return array{Pelanggan: array{Uuid: string, SaldoDeposit: string}, Berlaku: bool}|null
     */
    public function Ambil(string $uuid): ?array
    {
        $id = $this->identitas->CariId(strtoupper($uuid));
        $pelanggan = $id === null ? null : Pelanggan::query()->whereKey($id)->where('Status', StatusPelanggan::Aktif->value)->first(['Id', 'Uuid', 'SaldoDeposit']);

        if ($pelanggan === null) {
            return null;
        }

        return [
            'Pelanggan' => ['Uuid' => $pelanggan->Uuid, 'SaldoDeposit' => (string) $pelanggan->SaldoDeposit],
            'Berlaku' => $this->pengaturan->CekBerlaku(),
        ];
    }
}
