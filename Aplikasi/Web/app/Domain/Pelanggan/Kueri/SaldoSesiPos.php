<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Katalog\Kueri\DefinisiPaketSesi;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\SaldoSesi;

/**
 * Paket sesi aktif pelanggan untuk kasir (F-16d bagian 2, wajib online saat memakai sesi): sisa sesi, masa berlaku, dan
 * layanan yang boleh ditukar. Pelanggan dicari dari Uuid atau alias Uuid perangkat; null bila tidak ada, milik tenant
 * lain, atau diarsipkan. `Berlaku` = paket langganan punya fitur `pelanggan.paket-sesi`.
 */
final class SaldoSesiPos
{
    public function __construct(
        private readonly IdentitasPelanggan $identitas,
        private readonly PengaturanSesiTenant $pengaturan,
        private readonly DefinisiPaketSesi $definisi,
    ) {}

    /**
     * @return array{Pelanggan: array{Uuid: string}, Berlaku: bool, Paket: list<array{Uuid: string, NamaPaket: string, JumlahSesi: int, SisaSesi: int, BerlakuSampai: string|null, NomorPenjualan: string, SemuaProdukJasa: bool, ProdukBerlaku: list<array{Uuid: string, Nama: string}>}>}|null
     */
    public function Ambil(string $uuid): ?array
    {
        $id = $this->identitas->CariId(strtoupper($uuid));
        $pelanggan = $id === null ? null : Pelanggan::query()->whereKey($id)->where('Status', StatusPelanggan::Aktif->value)->first(['Id', 'Uuid']);

        if ($pelanggan === null) {
            return null;
        }

        $saldo = SaldoSesi::query()
            ->where('IdPelanggan', $pelanggan->Id)
            ->where('Status', StatusSaldoSesi::Aktif->value)
            ->orderByRaw('BerlakuSampai IS NULL')
            ->orderBy('BerlakuSampai')
            ->orderBy('Id')
            ->limit(50)
            ->get();
        $produk = $this->definisi->AmbilProdukBerlaku(array_values(array_map('intval', $saldo->pluck('IdPaketSesi')->unique()->all())));

        return [
            'Pelanggan' => ['Uuid' => $pelanggan->Uuid],
            'Berlaku' => $this->pengaturan->CekBerlaku(),
            'Paket' => array_values($saldo->map(fn (SaldoSesi $s): array => [
                'Uuid' => $s->Uuid,
                'NamaPaket' => $s->NamaPaket,
                'JumlahSesi' => $s->JumlahSesi,
                'SisaSesi' => $s->SisaSesi,
                'BerlakuSampai' => $s->BerlakuSampai?->toDateString(),
                'NomorPenjualan' => $s->NomorPenjualan,
                'SemuaProdukJasa' => $produk[$s->IdPaketSesi]['Semua'] ?? false,
                'ProdukBerlaku' => $produk[$s->IdPaketSesi]['Produk'] ?? [],
            ])->all()),
        ];
    }
}
