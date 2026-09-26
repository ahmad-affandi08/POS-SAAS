<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Kueri;

use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Layanan\KatalogPenyediaGerbang;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use Illuminate\Support\Carbon;

/**
 * Katalog gerbang pembayaran untuk tenant (P-05 v2.06) di halaman integrasi pengelola: penyedia dengan status
 * diizinkan, jumlah tenant yang memakainya, dan kesehatan webhook (tenant dengan notifikasi sah/ditolak 24 jam
 * terakhir). Agregat lintas tenant dibaca lewat `KonteksPengelola::JalankanLintasTenant` dan hanya memilih kolom
 * status; kredensial, pengaturan, dan token webhook tenant tidak pernah dibaca (aturan BackendPengelola).
 */
final class RingkasanGerbangTenant
{
    public function __construct(
        private readonly KonteksPengelola $konteks,
        private readonly KatalogPenyediaGerbang $katalog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Ambil(): array
    {
        $batas = Carbon::now()->subDay();
        /** @var array<string, array{JumlahTenant: int, JumlahAktif: int, JumlahUjiGagal: int, WebhookDiterima24Jam: int, WebhookDitolak24Jam: int}> $hitungan */
        $hitungan = $this->konteks->JalankanLintasTenant('P-05 v2.06: ringkasan pemakaian gerbang pembayaran tenant', function (KonteksPengelola $k) use ($batas): array {
            $hasil = [];
            $baris = $k->KueriLintas(GerbangPembayaranTenant::class)
                ->get(['Penyedia', 'Aktif', 'StatusUji', 'WebhookDiterimaPada', 'WebhookDitolakPada']);

            foreach ($baris as $gerbang) {
                $kode = $gerbang->Penyedia->value;
                $hasil[$kode] ??= ['JumlahTenant' => 0, 'JumlahAktif' => 0, 'JumlahUjiGagal' => 0, 'WebhookDiterima24Jam' => 0, 'WebhookDitolak24Jam' => 0];
                $hasil[$kode]['JumlahTenant']++;
                $hasil[$kode]['JumlahAktif'] += $gerbang->Aktif ? 1 : 0;
                $hasil[$kode]['JumlahUjiGagal'] += $gerbang->StatusUji->value === 'Gagal' ? 1 : 0;
                $hasil[$kode]['WebhookDiterima24Jam'] += $gerbang->WebhookDiterimaPada?->greaterThanOrEqualTo($batas) === true ? 1 : 0;
                $hasil[$kode]['WebhookDitolak24Jam'] += $gerbang->WebhookDitolakPada?->greaterThanOrEqualTo($batas) === true ? 1 : 0;
            }

            return $hasil;
        });
        $status = $this->katalog->AmbilStatus();

        return array_map(fn (PenyediaGerbang $penyedia): array => [
            'Penyedia' => $penyedia->value,
            'Label' => $penyedia->AmbilLabel(),
            'Diizinkan' => $status[$penyedia->value],
            ...($hitungan[$penyedia->value] ?? ['JumlahTenant' => 0, 'JumlahAktif' => 0, 'JumlahUjiGagal' => 0, 'WebhookDiterima24Jam' => 0, 'WebhookDitolak24Jam' => 0]),
        ], PenyediaGerbang::cases());
    }
}
