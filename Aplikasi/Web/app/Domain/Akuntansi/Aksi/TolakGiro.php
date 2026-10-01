<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\JenisSumberGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Model\Giro;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Aksi\BatalkanPembayaranPiutang;
use App\Domain\Pelanggan\Model\PembayaranPiutang;
use App\Domain\Pembelian\Aksi\BatalkanPembayaranHutang;
use App\Domain\Pembelian\Model\PembayaranHutang;
use Illuminate\Support\Facades\DB;

/**
 * v3.42 (F-12): giro/cek mundur ditolak bank (kosong, tanda tangan tidak cocok, dibatalkan penerbit). Pelunasan
 * piutang/pembayaran hutang asalnya dibatalkan lewat aksi pembatalan domainnya (jurnal dibalik, piutang/hutang kembali
 * terbuka), lalu giro ditandai Ditolak. Alasan 5–255 karakter. Audit `giro.tolak`.
 */
final class TolakGiro
{
    public function __construct(
        private readonly BatalkanPembayaranPiutang $batalkanPiutang,
        private readonly BatalkanPembayaranHutang $batalkanHutang,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AlasanTidakValid, GiroSudahDiputuskan
     */
    public function Jalankan(Giro $giro, string $alasan, int $idPengguna): Giro
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan penolakan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($giro, $alasan, $idPengguna): Giro {
            $terkunci = Giro::query()->whereKey($giro->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status !== StatusGiro::Menunggu) {
                throw new PelanggaranAturanBisnis('GiroSudahDiputuskan', "Giro {$terkunci->NomorGiro} sudah {$terkunci->Status->AmbilLabel()}.");
            }

            $teks = mb_substr("Giro {$terkunci->NomorGiro} ditolak: {$alasan}", 0, 255);

            if ($terkunci->JenisSumber === JenisSumberGiro::PembayaranPiutang) {
                $this->batalkanPiutang->Jalankan(PembayaranPiutang::query()->whereKey($terkunci->IdSumber)->firstOrFail(), $teks, $idPengguna, olehGiro: true);
            } else {
                $this->batalkanHutang->Jalankan(PembayaranHutang::query()->whereKey($terkunci->IdSumber)->firstOrFail(), $teks, $idPengguna, olehGiro: true);
            }

            $terkunci->UbahStatus(StatusGiro::Ditolak);
            $terkunci->forceFill(['AlasanTolak' => $alasan, 'DiputuskanOleh' => $idPengguna, 'DiputuskanPada' => now()])->save();
            $this->audit->Catat('giro.tolak', $terkunci, ['Status' => StatusGiro::Menunggu->value], [
                'Status' => StatusGiro::Ditolak->value,
                'NomorGiro' => $terkunci->NomorGiro,
                'NomorSumber' => $terkunci->NomorSumber,
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
