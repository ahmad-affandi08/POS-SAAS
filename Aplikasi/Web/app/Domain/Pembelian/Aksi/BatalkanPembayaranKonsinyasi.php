<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pembelian\Model\PembayaranKonsinyasi;
use Illuminate\Support\Facades\DB;

/**
 * F-05i: membatalkan setoran konsinyasi (CLAUDE.md #8): jurnalnya dibalik bertanggal hari bisnis, hutang penitip
 * kembali bertambah. Alasan 5–255 karakter. Audit `konsinyasi.setor.batalkan`.
 */
final class BatalkanPembayaranKonsinyasi
{
    public function __construct(
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly BalikkanJurnal $balikkan,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AlasanTidakValid
     */
    public function Jalankan(PembayaranKonsinyasi $setoran, string $alasan, int $idPengguna): PembayaranKonsinyasi
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan pembatalan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($setoran, $alasan, $idPengguna): PembayaranKonsinyasi {
            $terkunci = PembayaranKonsinyasi::query()->whereKey($setoran->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === StatusDokumenTerposting::Dibatalkan) {
                return $terkunci;
            }

            $jurnal = $terkunci->IdJurnal === null ? null : $this->balikkan->Jalankan(
                $terkunci->IdJurnal,
                $this->tanggalBisnis->Hitung(null),
                mb_substr("Pembatalan setoran konsinyasi {$terkunci->Nomor}", 0, 255),
                JenisSumberJurnal::PembayaranKonsinyasi,
                $terkunci->Id,
                'Pembatalan',
                $idPengguna,
            );

            $terkunci->UbahStatus(StatusDokumenTerposting::Dibatalkan);
            $terkunci->fill(['IdJurnalPembatalan' => $jurnal?->idJurnal, 'AlasanBatal' => $alasan, 'DibatalkanOleh' => $idPengguna, 'DibatalkanPada' => now()])->save();

            $this->riwayat->Catat(PembayaranKonsinyasi::JENIS_DOKUMEN, $terkunci->Id, StatusDokumenTerposting::Diposting->value, StatusDokumenTerposting::Dibatalkan->value, $idPengguna, $alasan);
            $this->audit->Catat('konsinyasi.setor.batalkan', $terkunci, ['Status' => StatusDokumenTerposting::Diposting->value], [
                'Status' => StatusDokumenTerposting::Dibatalkan->value,
                'Nomor' => $terkunci->Nomor,
                'Alasan' => $alasan,
                'NomorJurnalPembatalan' => $jurnal?->nomor,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
