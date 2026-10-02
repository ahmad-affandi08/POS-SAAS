<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Aksi;

use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Layanan\TautanPersetujuanServis;
use App\Domain\Bengkel\Model\PerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerjaDetail;
use App\Domain\Bengkel\Tugas\KirimTautanPersetujuanServisTugas;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Minta persetujuan estimasi ke pelanggan (§9.10): buat tautan rahasia `/{slug}/servis/{token}` berlaku 7 hari
 * (membuat ulang = tautan lama langsung tidak berlaku), status menjadi Menunggu persetujuan, lalu (bila diminta)
 * kirim tautannya lewat WhatsApp toko di antrean setelah commit. Tautan selalu bisa disalin dari back-office walau
 * WhatsApp belum diatur. Token tidak masuk log audit.
 */
final class MintaPersetujuanServis
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /** @return string token baru (bukan tautan; tautan disusun pemanggil dari slug tenant) */
    public function Jalankan(string $uuidPerintahKerja, int $idPengguna, bool $kirimWhatsapp): string
    {
        return DB::transaction(function () use ($uuidPerintahKerja, $idPengguna, $kirimWhatsapp): string {
            $pk = PerintahKerja::query()->where('Uuid', $uuidPerintahKerja)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('PerintahKerjaTidakDikenal', 'Perintah kerja tidak ditemukan.', 'Umum', 404);

            if (! in_array($pk->Status, [StatusPerintahKerja::Diterima, StatusPerintahKerja::Diagnosis, StatusPerintahKerja::MenungguPersetujuan], true)) {
                throw new PelanggaranAturanBisnis(
                    'PersetujuanTidakBisaDiminta',
                    "Perintah kerja {$pk->Nomor} berstatus {$pk->Status->AmbilLabel()}. Persetujuan hanya diminta untuk estimasi yang belum diputuskan.",
                );
            }

            if (! PerintahKerjaDetail::query()->where('IdPerintahKerja', $pk->Id)->exists()) {
                throw new PelanggaranAturanBisnis('EstimasiKosong', 'Isi jasa atau sparepart estimasinya dulu sebelum meminta persetujuan.', 'Baris');
            }

            $token = Str::random(TautanPersetujuanServis::PANJANG_TOKEN);
            $dari = $pk->Status;
            $pk->forceFill([
                'TokenPersetujuan' => $token,
                'HashTokenPersetujuan' => TautanPersetujuanServis::Hash($token),
                'TokenPersetujuanKedaluwarsaPada' => now()->addDays(TautanPersetujuanServis::HARI_BERLAKU),
                'PersetujuanDikirimPada' => null,
            ]);

            if ($dari !== StatusPerintahKerja::MenungguPersetujuan) {
                $pk->UbahStatus(StatusPerintahKerja::MenungguPersetujuan);
            }

            $pk->save();

            if ($dari !== StatusPerintahKerja::MenungguPersetujuan) {
                $this->riwayat->Catat(PerintahKerja::JENIS_DOKUMEN, $pk->Id, $dari->value, StatusPerintahKerja::MenungguPersetujuan->value, $idPengguna);
            }

            $this->audit->Catat('bengkel.persetujuan-minta', $pk, null, ['Nomor' => $pk->Nomor, 'KirimWhatsapp' => $kirimWhatsapp], idPengguna: $idPengguna);

            if ($kirimWhatsapp) {
                KirimTautanPersetujuanServisTugas::dispatch($pk->IdTenant, $pk->Id)->afterCommit();
            }

            return $token;
        });
    }
}
