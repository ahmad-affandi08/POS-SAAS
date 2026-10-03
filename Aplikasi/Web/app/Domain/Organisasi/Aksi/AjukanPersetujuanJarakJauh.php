<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Enum\StatusPermintaanPersetujuan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use App\Domain\Organisasi\Tugas\BuatNotifikasiPersetujuanTugas;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Kasir meminta persetujuan jarak jauh (X4, §19.2) karena penyetuju tidak di tempat. Idempoten per Uuid (permintaan
 * sama dari perangkat yang sama dikembalikan apa adanya). Fitur paket `persetujuan.jarak-jauh`; izin yang dimintakan
 * harus salah satu izin persetujuan kasir (atau khusus pemilik). Berlaku [MENIT_BERLAKU] menit. Audit
 * `persetujuan-jarak-jauh.ajukan`.
 */
final class AjukanPersetujuanJarakJauh
{
    public const MENIT_BERLAKU = 10;

    public const KUNCI_FITUR = 'persetujuan.jarak-jauh';

    /** Izin persetujuan di kasir yang boleh dimintakan dari jarak jauh. */
    public const IZIN_BOLEH = [
        IzinTenant::KasKeluarSetujui,
        IzinTenant::PenjualanDiskonSetujui,
        IzinTenant::ShiftSelisihSetujui,
        IzinTenant::PenjualanVoid,
        IzinTenant::PenjualanRetur,
        IzinTenant::PenjualanReturTanpaStruk,
        IzinTenant::PenjualanTempoSetujui,
    ];

    public function __construct(
        private readonly AnggotaOutlet $anggota,
        private readonly PemeriksaFiturTenant $fitur,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<array{Label: string, Nilai: string}>  $rincian
     */
    public function Jalankan(
        Perangkat $perangkat,
        string $uuid,
        string $uuidPemohon,
        ?IzinTenant $izin,
        string $judul,
        array $rincian,
        ?Uang $nilai,
    ): PermintaanPersetujuan {
        $lama = PermintaanPersetujuan::query()->where('Uuid', $uuid)->first();

        if ($lama !== null) {
            if ($lama->IdPerangkat !== $perangkat->Id) {
                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode permintaan sudah dipakai perangkat lain.', 'Uuid', 409);
            }

            return $lama;
        }

        if (! $this->fitur->CekAktif($perangkat->IdTenant, self::KUNCI_FITUR)) {
            throw new PelanggaranAturanBisnis('FiturTidakTersedia', 'Persetujuan jarak jauh belum termasuk paket usaha ini. Minta supervisor memasukkan PIN di perangkat.', 'Umum', 403);
        }

        if ($izin !== null && ! in_array($izin, self::IZIN_BOLEH, true)) {
            throw new PelanggaranAturanBisnis('IzinTidakDidukung', 'Jenis persetujuan ini tidak bisa diminta dari jarak jauh.', 'Izin');
        }

        $pemohon = $this->anggota->Cari($perangkat->IdTenant, $uuidPemohon, $perangkat->IdOutlet)
            ?? throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Pemohon bukan anggota aktif outlet ini.', 'UuidPengguna', 403);

        try {
            return DB::transaction(function () use ($perangkat, $uuid, $pemohon, $izin, $judul, $rincian, $nilai): PermintaanPersetujuan {
                $permintaan = PermintaanPersetujuan::query()->create([
                    'Uuid' => $uuid,
                    'IdOutlet' => $perangkat->IdOutlet,
                    'IdPerangkat' => $perangkat->Id,
                    'IdPemohon' => $pemohon->id,
                    'Izin' => $izin?->value,
                    'HanyaPemilik' => $izin === null,
                    'Judul' => mb_substr(trim($judul), 0, 150),
                    'Rincian' => $rincian,
                    'Nilai' => $nilai?->KeString(),
                    'KedaluwarsaPada' => now()->addMinutes(self::MENIT_BERLAKU),
                ]);
                $this->riwayat->Catat('PermintaanPersetujuan', $permintaan->Id, null, StatusPermintaanPersetujuan::Menunggu->value, $pemohon->id);
                $this->audit->Catat('persetujuan-jarak-jauh.ajukan', $permintaan, nilaiBaru: [
                    'Judul' => $permintaan->Judul,
                    'Izin' => $permintaan->Izin ?? 'Pemilik',
                    'Nilai' => $permintaan->Nilai,
                    'Perangkat' => $perangkat->Nama,
                ], idPengguna: $pemohon->id);
                BuatNotifikasiPersetujuanTugas::dispatch($permintaan->IdTenant, $permintaan->Id)->afterCommit();

                return $permintaan;
            });
        } catch (UniqueConstraintViolationException) {
            return PermintaanPersetujuan::query()->where('Uuid', $uuid)->firstOrFail();
        }
    }
}
