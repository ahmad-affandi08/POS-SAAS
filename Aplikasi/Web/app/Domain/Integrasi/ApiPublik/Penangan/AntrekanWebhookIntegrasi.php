<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Penangan;

use App\Domain\Bersama\Peristiwa\PeristiwaIntegrasi;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Layanan\PengantreWebhook;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;
use App\Domain\Katalog\Kueri\ProdukUntukApiPublik;
use App\Domain\Pelanggan\Kueri\PelangganUntukApiPublik;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * X7 §16.4 (v3.91): `PeristiwaIntegrasi` dari domain mana pun → kiriman webhook bagi webhook aktif tenant yang
 * melanggan, lewat `PengantreWebhook` (sama dengan peristiwa penjualan). Data sudah disusun domain pemiliknya.
 */
final class AntrekanWebhookIntegrasi implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PengantreWebhook $pengantre,
        private readonly ProdukUntukApiPublik $produk,
        private readonly PelangganUntukApiPublik $pelanggan,
    ) {}

    /**
     * Produk & pelanggan disusun ulang setelah commit dengan bentuk yang sama dengan `GET /api/v1/produk|pelanggan`
     * (harga & satuan bisa ikut berubah di aksi lain dalam transaksi yang sama); peristiwa lain membawa datanya sendiri.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function SusunData(PeristiwaWebhook $jenis, array $data): ?array
    {
        $uuid = is_string($data['Uuid'] ?? null) ? $data['Uuid'] : null;

        return match ($jenis) {
            PeristiwaWebhook::ProdukDiubah => $uuid === null ? null : ($this->produk->Daftar(0, 1, $uuid)['Data'][0] ?? null),
            PeristiwaWebhook::PelangganDibuat => $uuid === null ? null : ($this->pelanggan->Daftar(0, 1, $uuid)['Data'][0] ?? null),
            default => $data,
        };
    }

    /**
     * Hanya diantrekan bila tenant punya webhook aktif yang melanggan peristiwa itu (v3.97): perubahan produk dari impor
     * besar, form, atau data uji tidak membanjiri antrean untuk tenant yang tidak memakai webhook.
     */
    public function shouldQueue(PeristiwaIntegrasi $peristiwa): bool
    {
        if (PeristiwaWebhook::tryFrom($peristiwa->jenis) === null) {
            return false;
        }

        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($peristiwa->idTenant);

        try {
            return WebhookTenant::query()->where('Aktif', true)->get()
                ->contains(fn (WebhookTenant $w): bool => $w->CekBerlangganan($peristiwa->jenis));
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }
    }

    public function handle(PeristiwaIntegrasi $peristiwa): void
    {
        $jenis = PeristiwaWebhook::tryFrom($peristiwa->jenis);

        if ($jenis === null) {
            return;
        }

        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($peristiwa->idTenant);

        try {
            $this->pengantre->Antrekan($peristiwa->idTenant, $jenis, $peristiwa->idDokumen, $peristiwa->kunci, fn (): ?array => $this->SusunData($jenis, $peristiwa->data));
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }
    }
}
