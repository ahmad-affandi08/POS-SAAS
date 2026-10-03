<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Tugas;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Layanan\PengantreWebhook;
use App\Domain\Katalog\Kueri\ProdukUntukApiPublik;
use App\Domain\Pelanggan\Kueri\PelangganUntukApiPublik;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * X7 §16.4: menyusun data `PeristiwaIntegrasi` dan mencatat kirimannya lewat `PengantreWebhook`, di antrean (efek
 * non-kritis, aturan #10). Hanya dikirim `AntrekanWebhookIntegrasi` bila tenant punya webhook aktif yang melanggan
 * peristiwa itu (v3.98). Idempoten per (webhook, peristiwa, dokumen, kunci).
 */
final class AntrekanWebhookIntegrasiTugas implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $idTenant,
        public readonly string $jenis,
        public readonly int $idDokumen,
        public readonly string $kunci,
        public readonly array $data,
    ) {}

    public function handle(KonteksTenant $konteks, PengantreWebhook $pengantre, ProdukUntukApiPublik $produk, PelangganUntukApiPublik $pelanggan): void
    {
        $jenis = PeristiwaWebhook::tryFrom($this->jenis);

        if ($jenis === null) {
            return;
        }

        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($this->idTenant);

        try {
            $pengantre->Antrekan($this->idTenant, $jenis, $this->idDokumen, $this->kunci, fn (): ?array => $this->SusunData($jenis, $produk, $pelanggan));
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }

    /**
     * Produk & pelanggan disusun ulang setelah commit dengan bentuk yang sama dengan `GET /api/v1/produk|pelanggan`
     * (harga & satuan bisa ikut berubah di aksi lain dalam transaksi yang sama); peristiwa lain membawa datanya sendiri.
     *
     * @return array<string, mixed>|null
     */
    private function SusunData(PeristiwaWebhook $jenis, ProdukUntukApiPublik $produk, PelangganUntukApiPublik $pelanggan): ?array
    {
        $uuid = is_string($this->data['Uuid'] ?? null) ? $this->data['Uuid'] : null;

        return match ($jenis) {
            PeristiwaWebhook::ProdukDiubah => $uuid === null ? null : ($produk->Daftar(0, 1, $uuid)['Data'][0] ?? null),
            PeristiwaWebhook::PelangganDibuat => $uuid === null ? null : ($pelanggan->Daftar(0, 1, $uuid)['Data'][0] ?? null),
            default => $this->data,
        };
    }
}
