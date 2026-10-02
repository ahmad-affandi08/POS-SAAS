<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Aksi;

use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerjaDetail;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Support\Facades\DB;

/**
 * Tombol status perintah kerja di back-office (§9.10): Diagnosis (revisi estimasi), Dikerjakan, Qc, Selesai, atau
 * Dibatalkan (wajib alasan). Persetujuan dan penagihan punya jalurnya sendiri (`PutuskanPersetujuanServis`,
 * `PenagihPerintahKerjaPenjualan`) dan tidak bisa dipilih di sini.
 *
 * Kembali ke Diagnosis dari Menunggu persetujuan/Disetujui membatalkan persetujuan (baris kembali belum disetujui,
 * tautan lama tidak berlaku): estimasi yang akan direvisi harus disetujui ulang pelanggan.
 */
final class UbahStatusPerintahKerja
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuidPerintahKerja, StatusPerintahKerja $tujuan, int $idPengguna, ?string $alasan = null, ?string $catatanQc = null): PerintahKerja
    {
        return DB::transaction(function () use ($uuidPerintahKerja, $tujuan, $idPengguna, $alasan, $catatanQc): PerintahKerja {
            $pk = PerintahKerja::query()->where('Uuid', $uuidPerintahKerja)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('PerintahKerjaTidakDikenal', 'Perintah kerja tidak ditemukan.', 'Umum', 404);

            if ($pk->Status === $tujuan) {
                return $pk;
            }

            if (! in_array($tujuan, $pk->Status->AmbilTujuanManual(), true)) {
                throw new PelanggaranAturanBisnis(
                    'StatusTidakBisaDiubah',
                    "Perintah kerja {$pk->Nomor} berstatus {$pk->Status->AmbilLabel()} dan tidak bisa diubah ke {$tujuan->AmbilLabel()}.",
                    'Status',
                );
            }

            $alasan = $alasan === null ? null : trim($alasan);

            if ($tujuan === StatusPerintahKerja::Dibatalkan && ($alasan === null || mb_strlen($alasan) < 5)) {
                throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan pembatalan (minimal 5 huruf).', 'Alasan');
            }

            $dari = $pk->Status;
            $pk->UbahStatus($tujuan);

            if ($tujuan === StatusPerintahKerja::Diagnosis && in_array($dari, [StatusPerintahKerja::MenungguPersetujuan, StatusPerintahKerja::Disetujui], true)) {
                PerintahKerjaDetail::query()->where('IdPerintahKerja', $pk->Id)->update(['Disetujui' => false]);
                $pk->forceFill([
                    'TotalDisetujui' => '0.00',
                    'TokenPersetujuan' => null,
                    'HashTokenPersetujuan' => null,
                    'TokenPersetujuanKedaluwarsaPada' => null,
                    'DiputuskanPada' => null,
                    'DiputuskanLewat' => null,
                    'DiputuskanOleh' => null,
                    'HashIpPersetujuan' => null,
                ]);
            }

            if ($tujuan === StatusPerintahKerja::Dibatalkan) {
                $pk->AlasanBatal = mb_substr((string) $alasan, 0, 255);
                $pk->forceFill(['TokenPersetujuan' => null, 'HashTokenPersetujuan' => null, 'TokenPersetujuanKedaluwarsaPada' => null]);
            }

            if ($catatanQc !== null && trim($catatanQc) !== '' && in_array($tujuan, [StatusPerintahKerja::Qc, StatusPerintahKerja::Selesai, StatusPerintahKerja::Dikerjakan], true)) {
                $pk->CatatanQc = mb_substr(trim($catatanQc), 0, 500);
            }

            $pk->save();
            $this->riwayat->Catat(PerintahKerja::JENIS_DOKUMEN, $pk->Id, $dari->value, $tujuan->value, $idPengguna, $alasan === '' ? null : $alasan);
            $this->audit->Catat('bengkel.perintah-kerja-status', $pk, ['Status' => $dari->value], ['Status' => $tujuan->value, 'Alasan' => $alasan], idPengguna: $idPengguna);

            return $pk;
        });
    }
}
