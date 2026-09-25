<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Enum\StatusPembayaranPiutang;
use App\Domain\Pelanggan\Model\PembayaranPiutang;
use App\Domain\Pelanggan\Model\PembayaranPiutangAlokasi;
use App\Domain\Pelanggan\Model\Piutang;
use Illuminate\Support\Facades\DB;

/**
 * Batalkan pelunasan piutang (F-12, izin `akuntansi.kelola`): alasan 5–255 karakter, sisa piutang dikembalikan, jurnal
 * pembalik pada tanggal bisnis hari ini (dokumen asal tidak diubah, aturan #8). Idempoten. Audit
 * `pelunasan-piutang.batalkan`.
 */
final class BatalkanPembayaranPiutang
{
    public function __construct(
        private readonly BalikkanJurnal $balikkan,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PembayaranPiutang $pembayaran, string $alasan, int $idPengguna): PembayaranPiutang
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan pembatalan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($pembayaran, $alasan, $idPengguna): PembayaranPiutang {
            $alokasi = PembayaranPiutangAlokasi::query()->where('IdPembayaranPiutang', $pembayaran->Id)->get();
            $piutang = Piutang::query()->whereIn('Id', $alokasi->pluck('IdPiutang')->all())->orderBy('Id')->lockForUpdate()->get()->keyBy('Id');
            $terkunci = PembayaranPiutang::query()->whereKey($pembayaran->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === StatusPembayaranPiutang::Dibatalkan) {
                return $terkunci;
            }

            foreach ($alokasi as $a) {
                /** @var Piutang $p */
                $p = $piutang->get($a->IdPiutang);
                $asal = $p->Status;
                $p->JumlahDibayar = Uang::Dari($p->JumlahDibayar)->Kurangi(Uang::Dari($a->Jumlah))->KeString();
                $p->SelaraskanStatus();
                $p->save();

                if ($asal !== $p->Status) {
                    $this->riwayat->Catat(Piutang::JENIS_DOKUMEN, $p->Id, $asal->value, $p->Status->value, $idPengguna, $alasan);
                }
            }

            $jurnal = $terkunci->IdJurnal === null ? null : $this->balikkan->Jalankan(
                $terkunci->IdJurnal,
                $this->tanggalBisnis->Hitung(null),
                mb_substr("Pembatalan pelunasan piutang {$terkunci->Nomor}", 0, 255),
                JenisSumberJurnal::PembayaranPiutang,
                $terkunci->Id,
                'Pembatalan',
                $idPengguna,
            );

            $terkunci->fill([
                'Status' => StatusPembayaranPiutang::Dibatalkan,
                'IdJurnalPembatalan' => $jurnal?->idJurnal,
                'AlasanBatal' => $alasan,
                'DibatalkanOleh' => $idPengguna,
                'DibatalkanPada' => now(),
            ])->save();

            $this->riwayat->Catat(PembayaranPiutang::JENIS_DOKUMEN, $terkunci->Id, StatusPembayaranPiutang::Diposting->value, StatusPembayaranPiutang::Dibatalkan->value, $idPengguna, $alasan);
            $this->audit->Catat('pelunasan-piutang.batalkan', $terkunci, ['Status' => StatusPembayaranPiutang::Diposting->value], [
                'Status' => StatusPembayaranPiutang::Dibatalkan->value,
                'Nomor' => $terkunci->Nomor,
                'Alasan' => $alasan,
                'NomorJurnalPembatalan' => $jurnal?->nomor,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
