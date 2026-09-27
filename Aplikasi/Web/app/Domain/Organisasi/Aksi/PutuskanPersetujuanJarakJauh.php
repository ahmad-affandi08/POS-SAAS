<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\StatusPermintaanPersetujuan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan persetujuan jarak jauh dari Aplikasi Owner (X4, OWN-03): setujui atau tolak (alasan 5–255 karakter).
 * Penyetuju wajib anggota outlet permintaan dengan izin yang diminta (atau pemilik untuk permintaan khusus pemilik) dan
 * bukan pemohon sendiri (four-eyes). Permintaan yang sudah diputuskan/dibatalkan/lewat waktu ditolak (409); keputusan
 * sama yang dikirim ulang oleh orang yang sama dikembalikan apa adanya. Audit `persetujuan-jarak-jauh.setujui|tolak`.
 */
final class PutuskanPersetujuanJarakJauh
{
    public function __construct(
        private readonly AnggotaOutlet $anggota,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PermintaanPersetujuan $permintaan, string $uuidPenyetuju, bool $setujui, ?string $alasan = null): PermintaanPersetujuan
    {
        $penyetuju = $this->anggota->Cari($permintaan->IdTenant, $uuidPenyetuju, $permintaan->IdOutlet);

        if ($penyetuju === null || ! self::CekBolehMemutuskan($permintaan, $penyetuju)) {
            throw new PelanggaranAturanBisnis('TanpaIzin', 'Anda tidak berwenang memutuskan permintaan ini.', 'Umum', 403);
        }

        if ($penyetuju->id === $permintaan->IdPemohon) {
            throw new PelanggaranAturanBisnis('PenyetujuSamaDenganPemohon', 'Permintaan harus diputuskan orang lain, bukan pemohon sendiri.', 'Umum', 403);
        }

        $alasan = $alasan === null ? null : trim($alasan);

        if (! $setujui && ($alasan === null || mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255)) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan penolakan 5–255 karakter agar kasir tahu.', 'Alasan');
        }

        $hasil = DB::transaction(function () use ($permintaan, $penyetuju, $setujui, $alasan): ?PermintaanPersetujuan {
            $p = PermintaanPersetujuan::query()->whereKey($permintaan->Id)->lockForUpdate()->firstOrFail();
            $tujuan = $setujui ? StatusPermintaanPersetujuan::Disetujui : StatusPermintaanPersetujuan::Ditolak;

            if ($p->Status === $tujuan && $p->DiputuskanOleh === $penyetuju->id) {
                return $p;
            }

            if ($p->CekLewatWaktu()) {
                // Status Kedaluwarsa disimpan dulu (transaksi selesai), lalu keputusan ditolak di luar transaksi.
                $this->Kedaluwarsakan($p);

                return null;
            }

            $dari = $p->Status->value;
            $p->UbahStatus($tujuan);
            $p->forceFill([
                'DiputuskanOleh' => $penyetuju->id,
                'DiputuskanPada' => now(),
                'AlasanTolak' => $setujui ? null : $alasan,
            ])->save();
            $this->riwayat->Catat('PermintaanPersetujuan', $p->Id, $dari, $tujuan->value, $penyetuju->id, $setujui ? null : $alasan);
            $this->audit->Catat($setujui ? 'persetujuan-jarak-jauh.setujui' : 'persetujuan-jarak-jauh.tolak', $p, nilaiBaru: [
                'Judul' => $p->Judul,
                'Nilai' => $p->Nilai,
                'Alasan' => $alasan,
            ], idPengguna: $penyetuju->id);

            return $p;
        });

        return $hasil ?? throw new PelanggaranAturanBisnis('SudahKedaluwarsa', 'Permintaan ini sudah kedaluwarsa. Kasir perlu meminta lagi.', 'Umum', 409);
    }

    public static function CekBolehMemutuskan(PermintaanPersetujuan $p, DataAnggotaOutlet $anggota): bool
    {
        if ($anggota->pemilik) {
            return true;
        }

        return ! $p->HanyaPemilik && $p->Izin !== null && $anggota->CekIzin($p->Izin);
    }

    /** Tandai Kedaluwarsa (dipanggil di dalam transaksi dengan baris terkunci). */
    public function Kedaluwarsakan(PermintaanPersetujuan $p): void
    {
        $p->UbahStatus(StatusPermintaanPersetujuan::Kedaluwarsa);
        $p->save();
        $this->riwayat->Catat('PermintaanPersetujuan', $p->Id, StatusPermintaanPersetujuan::Menunggu->value, StatusPermintaanPersetujuan::Kedaluwarsa->value, null);
    }
}
