<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\KuponLanggananPemakaian;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Illuminate\Support\Facades\DB;

/**
 * Owner membatalkan tagihan terbuka yang belum dibayar (P-08: Terbit → Dibatalkan), misal salah pilih paket.
 * Nomor tagihan tetap tercatat (tanpa celah, BR-P08.1). Ditolak bila bukti transfer sedang diverifikasi.
 * Pemakaian kupon tagihan ini dilepas sehingga kupon bisa dipakai lagi.
 */
final class BatalkanTagihanLangganan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(string $uuidTagihan, string $alasan): TagihanLangganan
    {
        return DB::transaction(function () use ($uuidTagihan, $alasan): TagihanLangganan {
            $tagihan = TagihanLangganan::query()->where('Uuid', $uuidTagihan)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('TagihanTidakDitemukan', 'Tagihan tidak ditemukan.');

            if (! $tagihan->Status->CekTerbuka()) {
                throw new PelanggaranAturanBisnis('TagihanTidakTerbuka', "Tagihan {$tagihan->Nomor} sudah {$tagihan->Status->AmbilLabel()}.");
            }

            $menunggu = PembayaranLangganan::query()
                ->where('IdTagihanLangganan', $tagihan->Id)
                ->where('Status', StatusPembayaranLangganan::Menunggu->value)
                ->exists();

            if ($menunggu) {
                throw new PelanggaranAturanBisnis('PembayaranMasihDiverifikasi', 'Tagihan tidak bisa dibatalkan karena bukti transfernya sedang diverifikasi.');
            }

            $statusLama = $tagihan->Status;
            $tagihan->update([
                'Status' => StatusTagihanLangganan::Dibatalkan,
                'DibatalkanPada' => now(),
                'AlasanBatal' => $alasan,
            ]);

            KuponLanggananPemakaian::query()
                ->where('IdTenant', $tagihan->IdTenant)
                ->where('IdTagihanLangganan', $tagihan->Id)
                ->whereNull('DibatalkanPada')
                ->update(['DibatalkanPada' => now()]);

            $this->audit->Catat(
                'langganan.tagihan-batal',
                $tagihan,
                nilaiLama: ['Status' => $statusLama->value],
                nilaiBaru: ['Status' => $tagihan->Status->value, 'Nomor' => $tagihan->Nomor, 'Alasan' => $alasan],
            );

            return $tagihan;
        });
    }
}
