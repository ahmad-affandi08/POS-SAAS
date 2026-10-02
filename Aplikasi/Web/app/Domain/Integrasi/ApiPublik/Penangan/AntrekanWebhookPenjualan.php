<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Penangan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Enum\StatusKirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Layanan\PenyusunDataPenjualanApi;
use App\Domain\Integrasi\ApiPublik\Model\KirimanWebhook;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;
use App\Domain\Integrasi\ApiPublik\Tugas\KirimWebhookTugas;
use App\Domain\Penjualan\Peristiwa\PenjualanDiterima;
use App\Domain\Penjualan\Peristiwa\PenjualanDivoid;
use App\Domain\Penjualan\Peristiwa\PeristiwaDokumenPenjualan;
use App\Domain\Penjualan\Peristiwa\ReturPenjualanDiterima;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * X7 bagian 2 (PRD §16.4): setelah penjualan diterima, di-void, atau diretur (peristiwa domain Penjualan, setelah
 * commit), bekukan muatan `{IdPeristiwa, Peristiwa, TerjadiPada, Data}` untuk tiap webhook aktif tenant yang
 * melanggan, lalu antrekan pengirimannya. Hanya bila paket masih ber-fitur `api.publik`. Idempoten: kiriman unik per
 * (webhook, peristiwa, dokumen), jadi penangan yang diulang tidak menggandakan kiriman.
 */
final class AntrekanWebhookPenjualan implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaFiturTenant $fitur,
        private readonly PenyusunDataPenjualanApi $penyusun,
    ) {}

    public function handle(PeristiwaDokumenPenjualan $peristiwa): void
    {
        [$jenis, $idDokumen] = match (true) {
            $peristiwa instanceof PenjualanDiterima => [PeristiwaWebhook::PenjualanSelesai, $peristiwa->idDokumen],
            $peristiwa instanceof PenjualanDivoid => [PeristiwaWebhook::PenjualanDivoid, $peristiwa->idDokumen],
            $peristiwa instanceof ReturPenjualanDiterima => [PeristiwaWebhook::PenjualanDiretur, $peristiwa->idDokumen],
            default => [null, 0],
        };

        if ($jenis === null) {
            return;
        }

        $sebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($peristiwa->AmbilIdTenant());

        try {
            $this->Antrekan($peristiwa->AmbilIdTenant(), $jenis, $idDokumen);
        } finally {
            $sebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($sebelumnya);
        }
    }

    private function Antrekan(int $idTenant, PeristiwaWebhook $jenis, int $idDokumen): void
    {
        $webhook = WebhookTenant::query()->where('Aktif', true)->get()
            ->filter(fn (WebhookTenant $w): bool => $w->CekBerlangganan($jenis->value));

        if ($webhook->isEmpty() || ! $this->fitur->CekAktif($idTenant, 'api.publik')) {
            return;
        }

        $data = $jenis === PeristiwaWebhook::PenjualanDiretur
            ? $this->penyusun->Retur($idDokumen)
            : $this->penyusun->SatuBerdasarkanId($idDokumen);

        if ($data === null) {
            return;
        }

        foreach ($webhook as $w) {
            $idPeristiwa = (string) Str::ulid();
            $kiriman = KirimanWebhook::query()->firstOrCreate(
                ['IdWebhookTenant' => $w->Id, 'Peristiwa' => $jenis->value, 'IdDokumen' => $idDokumen],
                [
                    'Uuid' => $idPeristiwa,
                    'Muatan' => ['IdPeristiwa' => $idPeristiwa, 'Peristiwa' => $jenis->value, 'TerjadiPada' => now()->toIso8601ZuluString(), 'Data' => $data],
                    'Status' => StatusKirimanWebhook::Menunggu,
                    'BerikutnyaPada' => now(),
                ],
            );

            if ($kiriman->wasRecentlyCreated) {
                KirimWebhookTugas::dispatch($idTenant, $kiriman->Id);
            }
        }
    }
}
