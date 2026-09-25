<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\BukuPoin;
use App\Domain\Pelanggan\Model\Pelanggan;

/**
 * Saldo poin terkini & aturan tukar untuk kasir sebelum menukar poin (F-16b bagian 2, §18.4: penukaran wajib online).
 * Pelanggan dicari dari Uuid atau alias Uuid perangkat; null bila tidak ada atau diarsipkan.
 */
final class SaldoPoinPos
{
    public function __construct(
        private readonly IdentitasPelanggan $identitas,
        private readonly PengaturanLoyaltiTenant $pengaturan,
        private readonly BukuPoin $buku,
    ) {}

    /**
     * @return array{Pelanggan: array{Uuid: string, SaldoPoin: int}, TukarPoin: array{Berlaku: bool, NilaiTukarPoin: string, MinimalTukarPoin: int}}|null
     */
    public function Ambil(string $uuid): ?array
    {
        $id = $this->identitas->CariId(strtoupper($uuid));
        $pelanggan = $id === null ? null : Pelanggan::query()->whereKey($id)->where('Status', StatusPelanggan::Aktif->value)->first(['Id', 'Uuid']);

        if ($pelanggan === null) {
            return null;
        }

        $aturan = $this->pengaturan->Ambil();

        return [
            'Pelanggan' => ['Uuid' => $pelanggan->Uuid, 'SaldoPoin' => $this->buku->AmbilSaldo($pelanggan->Id)],
            'TukarPoin' => [
                'Berlaku' => $aturan->CekBerlaku(),
                'NilaiTukarPoin' => (string) $aturan->nilaiTukarPoin->toScale(2),
                'MinimalTukarPoin' => $aturan->minimalTukarPoin,
            ],
        ];
    }
}
