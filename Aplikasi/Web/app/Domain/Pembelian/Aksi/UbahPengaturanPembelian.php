<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Aksi\UbahPengaturanPembelianTenant;
use App\Domain\Tenant\Data\DataPengaturanPembelian;
use App\Domain\Tenant\Kueri\PengaturanPembelianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah pengaturan pembelian tenant (F-04 fase 1): `BatasPersetujuanPo` (Rp 0 – Rp 1 triliun; 0 = setiap PO butuh
 * persetujuan) dan `ToleransiPenerimaanPersen` (0–100%, BR-04.1). Berlaku untuk pengajuan PO & penerimaan berikutnya.
 * Tanpa perubahan = tidak ada yang ditulis. Audit `pembelian.pengaturan.ubah`.
 */
final class UbahPengaturanPembelian
{
    private const BATAS_MAKSIMAL = '1000000000000.00';

    public function __construct(
        private readonly PengaturanPembelianTenant $pengaturan,
        private readonly UbahPengaturanPembelianTenant $ubahTenant,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis BatasPersetujuanTidakValid, ToleransiTidakValid
     */
    public function Jalankan(DataPengaturanPembelian $baru, int $idPengguna): void
    {
        if ($baru->batasPersetujuanPo->BernilaiNegatif() || $baru->batasPersetujuanPo->Bandingkan(Uang::Dari(self::BATAS_MAKSIMAL)) > 0) {
            throw new PelanggaranAturanBisnis('BatasPersetujuanTidakValid', 'Batas persetujuan PO harus antara Rp 0 dan Rp 1 triliun.', 'BatasPersetujuanPo');
        }

        if ($baru->toleransiPenerimaanPersen->isNegative() || $baru->toleransiPenerimaanPersen->isGreaterThan(DataPengaturanPembelian::TOLERANSI_PENERIMAAN_MAKSIMAL)) {
            throw new PelanggaranAturanBisnis('ToleransiTidakValid', 'Toleransi penerimaan harus antara 0% dan 100%.', 'ToleransiPenerimaanPersen');
        }

        DB::transaction(function () use ($baru, $idPengguna): void {
            $lama = $this->pengaturan->Ambil();
            $nilaiLama = ['BatasPersetujuanPo' => $lama->batasPersetujuanPo->KeString(), 'ToleransiPenerimaanPersen' => (string) $lama->toleransiPenerimaanPersen, 'DrafPoOtomatis' => $lama->drafPoOtomatis];
            $nilaiBaru = ['BatasPersetujuanPo' => $baru->batasPersetujuanPo->KeString(), 'ToleransiPenerimaanPersen' => (string) $baru->toleransiPenerimaanPersen, 'DrafPoOtomatis' => $baru->drafPoOtomatis];

            if ($nilaiLama === $nilaiBaru) {
                return;
            }

            $this->ubahTenant->Jalankan($baru);
            $this->audit->Catat('pembelian.pengaturan.ubah', nilaiLama: $nilaiLama, nilaiBaru: $nilaiBaru, idPengguna: $idPengguna);
        });
    }
}
