<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\ApiPublik\Enum\PeristiwaWebhook;
use App\Domain\Integrasi\ApiPublik\Layanan\PenjagaAlamatWebhook;
use App\Domain\Integrasi\ApiPublik\Model\WebhookTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * X7 bagian 2: Owner mendaftarkan webhook keluar (alamat HTTPS publik + peristiwa). Hanya bila paket ber-fitur
 * `api.publik`; paling banyak [BATAS] webhook per tenant. Rahasia penandatangan dibuat server dan dikembalikan sekali.
 */
final class BuatWebhook
{
    public const BATAS = 5;

    public function __construct(
        private readonly PemeriksaFiturTenant $fitur,
        private readonly PenjagaAlamatWebhook $penjaga,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $peristiwa
     * @return array{Rahasia: string, Model: WebhookTenant}
     */
    public function Jalankan(int $idTenant, int $idPengguna, string $nama, string $url, array $peristiwa): array
    {
        if (! $this->fitur->CekAktif($idTenant, 'api.publik')) {
            throw new PelanggaranAturanBisnis('FiturTidakTersedia', 'Webhook tidak termasuk paket langganan Anda. Naikkan paket atau tambahkan add-on dulu.');
        }

        $peristiwa = array_values(array_unique(array_filter($peristiwa, fn (string $p): bool => PeristiwaWebhook::tryFrom($p) !== null)));

        if ($peristiwa === []) {
            throw new PelanggaranAturanBisnis('PeristiwaKosong', 'Pilih minimal satu peristiwa untuk webhook ini.');
        }

        $url = trim($url);
        $periksa = $this->penjaga->Periksa($url);

        if (! $periksa['Aman']) {
            throw new PelanggaranAturanBisnis('AlamatWebhookTidakAman', $periksa['Alasan']);
        }

        return DB::transaction(function () use ($idPengguna, $nama, $url, $peristiwa): array {
            if (WebhookTenant::query()->lockForUpdate()->count() >= self::BATAS) {
                throw new PelanggaranAturanBisnis('BatasWebhook', 'Webhook sudah '.self::BATAS.'. Hapus webhook yang tidak dipakai dulu.');
            }

            $rahasia = Str::random(48);
            $model = WebhookTenant::query()->create([
                'Nama' => mb_substr(trim($nama), 0, 60),
                'Url' => $url,
                'Rahasia' => $rahasia,
                'Peristiwa' => $peristiwa,
                'DibuatOleh' => $idPengguna,
            ]);
            $this->audit->Catat('integrasi.webhook.buat', $model, null, ['Nama' => $model->Nama, 'Url' => $url, 'Peristiwa' => $peristiwa]);

            return ['Rahasia' => $rahasia, 'Model' => $model];
        });
    }
}
