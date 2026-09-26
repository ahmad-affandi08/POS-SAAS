<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Model\KatalogGerbangPembayaran;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Mengizinkan atau melarang satu penyedia gerbang pembayaran untuk tenant (P-05 v2.06). Melarang tidak menghapus
 * konfigurasi tenant: tenant yang memakainya tidak bisa membuat tagihan QRIS baru (409 `GerbangBelumAktif`) dan tidak
 * bisa mengaktifkannya lagi, tetapi tagihan yang sudah dibuat tetap bisa dicek & dilunasi lewat webhook. Melarang
 * wajib beralasan karena berdampak ke tenant yang sedang memakai penyedia itu. Tercatat di `LogAuditPengelola`.
 */
final class UbahIzinPenyediaGerbang
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, PenyediaGerbang $penyedia, bool $diizinkan, ?string $alasan): KatalogGerbangPembayaran
    {
        if (! $diizinkan && ($alasan === null || trim($alasan) === '')) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan melarang penyedia ini. Tenant yang memakainya tidak bisa membuat tagihan QRIS baru.', 'Alasan');
        }

        return DB::transaction(function () use ($pelaku, $penyedia, $diizinkan, $alasan): KatalogGerbangPembayaran {
            $baris = KatalogGerbangPembayaran::query()->where('Penyedia', $penyedia->value)->lockForUpdate()->first();
            $lama = $baris->Diizinkan ?? true;

            if ($lama === $diizinkan) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', $diizinkan ? 'Penyedia ini sudah diizinkan.' : 'Penyedia ini sudah dilarang.');
            }

            $baris ??= new KatalogGerbangPembayaran(['Penyedia' => $penyedia]);
            $baris->Diizinkan = $diizinkan;
            $baris->save();
            $this->audit->Catat(
                $diizinkan ? 'integrasi.gerbang.izinkan' : 'integrasi.gerbang.larang',
                $baris,
                nilaiLama: ['Penyedia' => $penyedia->value, 'Diizinkan' => $lama],
                nilaiBaru: ['Penyedia' => $penyedia->value, 'Diizinkan' => $diizinkan],
                alasan: $alasan === null || trim($alasan) === '' ? null : trim($alasan),
                idPelaku: $pelaku->Id,
            );

            return $baris;
        });
    }
}
