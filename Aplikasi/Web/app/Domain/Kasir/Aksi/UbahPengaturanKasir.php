<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Aksi\UbahPengaturanKasirTenant;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah batas kas keluar tanpa persetujuan (BR-06.4) dan mode shift bersama (BR-06.2) tenant (F-06). Batas
 * 0 = setiap kas keluar butuh persetujuan. Perubahan berlaku untuk shift/mutasi berikutnya setelah perangkat
 * memperbarui data; mutasi yang sudah diterima tidak dinilai ulang. Tanpa perubahan = tidak ada yang ditulis.
 * Audit `kasir.pengaturan.ubah`.
 */
final class UbahPengaturanKasir
{
    /** Batas atas wajar agar tidak melampaui DECIMAL(18,2). */
    private const BATAS_MAKSIMAL = '1000000000000.00';

    public function __construct(
        private readonly PengaturanKasirTenant $pengaturan,
        private readonly UbahPengaturanKasirTenant $ubahTenant,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Uang $batasKasKeluar, bool $shiftBersama): void
    {
        if ($batasKasKeluar->BernilaiNegatif() || $batasKasKeluar->Bandingkan(Uang::Dari(self::BATAS_MAKSIMAL)) > 0) {
            throw new PelanggaranAturanBisnis('BatasKasKeluarTidakValid', 'Batas kas keluar harus antara Rp 0 dan Rp 1 triliun.', 'BatasKasKeluar');
        }

        DB::transaction(function () use ($batasKasKeluar, $shiftBersama): void {
            $lama = $this->pengaturan->Ambil();

            if ($lama->batasKasKeluar->SamaDengan($batasKasKeluar) && $lama->shiftBersama === $shiftBersama) {
                return;
            }

            $this->ubahTenant->Jalankan(new DataPengaturanKasir($batasKasKeluar, $shiftBersama));
            $this->audit->Catat(
                'kasir.pengaturan.ubah',
                nilaiLama: ['BatasKasKeluar' => $lama->batasKasKeluar->KeString(), 'ShiftBersama' => $lama->shiftBersama],
                nilaiBaru: ['BatasKasKeluar' => $batasKasKeluar->KeString(), 'ShiftBersama' => $shiftBersama],
            );
        });
    }
}
