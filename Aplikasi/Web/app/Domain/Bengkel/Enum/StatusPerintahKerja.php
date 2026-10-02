<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Enum;

/**
 * Status perintah kerja bengkel (§9.10, SLS-08): keluhan → diagnosis → estimasi → persetujuan pelanggan → pengerjaan →
 * QC → selesai → ditagih. Perubahan hanya lewat `BisaBerubahKe()` dan dicatat di `RiwayatStatusDokumen`.
 *
 * - `Disetujui`/`Ditolak` hanya lewat keputusan persetujuan (tautan pelanggan atau dicatat staf), bukan tombol status.
 * - `Ditagih` hanya oleh penjualan kasir yang merujuknya (`Penjualan.Buat` `UuidPerintahKerja`); void penjualan itu
 *   mengembalikannya ke `Selesai`. Perintah kerja yang sudah disetujui boleh langsung ditagih (pelanggan bayar di
 *   muka atau QC di tempat) — kasir tidak boleh tertahan oleh status yang lupa diperbarui.
 * - Kembali ke `Diagnosis` (revisi estimasi) membatalkan persetujuan sebelumnya.
 * - `Dibatalkan` adalah status akhir; `Ditagih` hanya bisa kembali lewat void.
 */
enum StatusPerintahKerja: string
{
    case Diterima = 'Diterima';
    case Diagnosis = 'Diagnosis';
    case MenungguPersetujuan = 'MenungguPersetujuan';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';
    case Dikerjakan = 'Dikerjakan';
    case Qc = 'Qc';
    case Selesai = 'Selesai';
    case Ditagih = 'Ditagih';
    case Dibatalkan = 'Dibatalkan';

    public function BisaBerubahKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Diterima => [self::Diagnosis, self::MenungguPersetujuan, self::Disetujui, self::Ditolak, self::Dibatalkan],
            self::Diagnosis => [self::MenungguPersetujuan, self::Disetujui, self::Ditolak, self::Dibatalkan],
            self::MenungguPersetujuan => [self::Disetujui, self::Ditolak, self::Diagnosis, self::Dibatalkan],
            self::Disetujui => [self::Dikerjakan, self::Diagnosis, self::Dibatalkan, self::Ditagih],
            self::Ditolak => [self::Diagnosis, self::Disetujui, self::Dibatalkan],
            self::Dikerjakan => [self::Qc, self::Dibatalkan, self::Ditagih],
            self::Qc => [self::Selesai, self::Dikerjakan, self::Ditagih],
            self::Selesai => [self::Ditagih],
            self::Ditagih => [self::Selesai],
            self::Dibatalkan => [],
        }, true);
    }

    /** Isi (pelanggan, kendaraan, keluhan, baris) masih boleh diubah: belum ada persetujuan yang berlaku. */
    public function CekBolehDiubah(): bool
    {
        return in_array($this, [self::Diterima, self::Diagnosis, self::Ditolak], true);
    }

    /** Boleh ditagih kasir: sudah disetujui pelanggan dan belum ditagih/dibatalkan. */
    public function CekSiapTagih(): bool
    {
        return in_array($this, self::AmbilSiapTagih(), true);
    }

    /** Masih berjalan (belum ditagih/dibatalkan). */
    public function CekAktif(): bool
    {
        return $this !== self::Ditagih && $this !== self::Dibatalkan;
    }

    /** @return list<self> */
    public static function AmbilSiapTagih(): array
    {
        return [self::Disetujui, self::Dikerjakan, self::Qc, self::Selesai];
    }

    /** Boleh dimintakan / dicatat persetujuannya (estimasi sudah ada, belum disetujui). */
    public function CekBolehDiputuskan(): bool
    {
        return in_array($this, [self::Diterima, self::Diagnosis, self::MenungguPersetujuan, self::Ditolak], true);
    }

    /**
     * Tujuan yang boleh dipilih lewat tombol status back-office (bukan persetujuan, bukan tagih/void).
     *
     * @return list<self>
     */
    public function AmbilTujuanManual(): array
    {
        return array_values(array_filter(
            [self::Diagnosis, self::Dikerjakan, self::Qc, self::Selesai, self::Dibatalkan],
            fn (self $s): bool => $this->BisaBerubahKe($s),
        ));
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Diterima => 'Diterima',
            self::Diagnosis => 'Diagnosis',
            self::MenungguPersetujuan => 'Menunggu persetujuan',
            self::Disetujui => 'Disetujui pelanggan',
            self::Ditolak => 'Ditolak pelanggan',
            self::Dikerjakan => 'Dikerjakan',
            self::Qc => 'Pemeriksaan akhir (QC)',
            self::Selesai => 'Selesai, siap ditagih',
            self::Ditagih => 'Sudah ditagih',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
