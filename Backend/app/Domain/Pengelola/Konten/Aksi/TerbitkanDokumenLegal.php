<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use Illuminate\Support\Facades\DB;

/**
 * Menerbitkan draf dokumen legal (P-06, BR-P06.3). Tanggal berlaku tidak di masa lalu, lebih lambat dari versi terbit
 * sebelumnya, dan untuk versi materiil yang menggantikan versi lama minimal 30 hari setelah terbit (masa pengumuman).
 */
final class TerbitkanDokumenLegal
{
    public const HARI_PENGUMUMAN_MATERIIL = 30;

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DokumenLegal $dokumen): DokumenLegal
    {
        return DB::transaction(function () use ($pelaku, $dokumen): DokumenLegal {
            $sejenis = DokumenLegal::query()->where('Jenis', $dokumen->Jenis->value)->lockForUpdate()->get();
            $dokumen = $sejenis->firstWhere('Id', $dokumen->Id) ?? DokumenLegal::query()->findOrFail($dokumen->Id);

            if ($dokumen->Status !== StatusDokumenLegal::Draf) {
                throw new PelanggaranAturanBisnis('BR-P06.1', 'Versi ini sudah terbit.');
            }

            $hariIni = now('Asia/Jakarta')->startOfDay();
            $berlaku = $dokumen->BerlakuMulai->copy()->startOfDay();

            if ($berlaku->lt($hariIni->toDateString())) {
                throw new PelanggaranAturanBisnis('BR-P06.3', 'Tanggal berlaku sudah lewat. Pilih hari ini atau tanggal nanti.', 'BerlakuMulai');
            }

            $terbitTerakhir = $sejenis
                ->filter(fn (DokumenLegal $baris) => $baris->Status === StatusDokumenLegal::Terbit)
                ->sortByDesc(fn (DokumenLegal $baris) => $baris->BerlakuMulai->toDateString())
                ->first();

            if ($terbitTerakhir !== null) {
                if ($berlaku->lte($terbitTerakhir->BerlakuMulai->toDateString())) {
                    throw new PelanggaranAturanBisnis(
                        'BR-P06.3',
                        "Tanggal berlaku harus setelah versi {$terbitTerakhir->Versi} ({$terbitTerakhir->BerlakuMulai->translatedFormat('j F Y')}).",
                        'BerlakuMulai',
                    );
                }

                $palingCepat = $hariIni->copy()->addDays(self::HARI_PENGUMUMAN_MATERIIL);

                if ($dokumen->Materiil && $berlaku->lt($palingCepat->toDateString())) {
                    throw new PelanggaranAturanBisnis(
                        'BR-P06.3',
                        'Perubahan materiil wajib diumumkan minimal 30 hari. Tanggal berlaku paling cepat '.$palingCepat->translatedFormat('j F Y').'.',
                        'BerlakuMulai',
                    );
                }
            }

            $dokumen->update([
                'Status' => StatusDokumenLegal::Terbit,
                'IdPenggunaPengelolaPenerbit' => $pelaku->Id,
                'DiterbitkanPada' => now(),
            ]);

            $this->audit->Catat(
                'legal.terbitkan',
                $dokumen,
                nilaiBaru: [
                    'Jenis' => $dokumen->Jenis->value,
                    'Versi' => $dokumen->Versi,
                    'Materiil' => $dokumen->Materiil,
                    'BerlakuMulai' => $dokumen->BerlakuMulai->toDateString(),
                ],
                idPelaku: $pelaku->Id,
            );

            return $dokumen;
        });
    }
}
