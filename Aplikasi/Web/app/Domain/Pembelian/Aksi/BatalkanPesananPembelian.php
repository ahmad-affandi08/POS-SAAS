<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PesananPembelian;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan PO (Draf, MenungguPersetujuan, atau Disetujui) yang belum punya penerimaan barang aktif (F-04 fase 1),
 * atau menutup PO yang sudah menerima barang (sisa pesanan tidak ditunggu lagi). Alasan wajib 5–255 karakter untuk
 * pembatalan. Audit `pesanan-pembelian.batalkan`/`pesanan-pembelian.tutup`.
 */
final class BatalkanPesananPembelian
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis StatusTidakSesuai, SudahAdaPenerimaan, AlasanTidakValid
     */
    public function Jalankan(PesananPembelian $po, int $idPengguna, bool $tutup = false, ?string $alasan = null): PesananPembelian
    {
        $alasan = trim((string) $alasan);

        if (! $tutup && (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255)) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan pembatalan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($po, $idPengguna, $tutup, $alasan): PesananPembelian {
            $terkunci = PesananPembelian::query()->whereKey($po->Id)->lockForUpdate()->firstOrFail();
            $tujuan = $tutup ? StatusPesananPembelian::Ditutup : StatusPesananPembelian::Dibatalkan;

            if ($terkunci->Status === $tujuan) {
                return $terkunci;
            }

            $adaPenerimaan = PenerimaanBarang::query()->where('IdPesananPembelian', $terkunci->Id)->where('Status', StatusDokumenPembelian::Diposting->value)->exists();

            if (! $tutup && $adaPenerimaan) {
                throw new PelanggaranAturanBisnis('SudahAdaPenerimaan', 'Pesanan pembelian yang sudah menerima barang tidak bisa dibatalkan. Tutup pesanan bila sisa barang tidak akan datang.');
            }

            if (! $terkunci->Status->BisaBerubahKe($tujuan) || ($tutup && $terkunci->Status === StatusPesananPembelian::Disetujui && ! $adaPenerimaan)) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Pesanan pembelian berstatus {$terkunci->Status->AmbilLabel()} tidak bisa ".($tutup ? 'ditutup' : 'dibatalkan').'.');
            }

            $asal = $terkunci->Status;
            $terkunci->UbahStatus($tujuan);
            $terkunci->fill($tutup
                ? ['DitutupOleh' => $idPengguna, 'DitutupPada' => now(), 'DiubahOleh' => $idPengguna]
                : ['DibatalkanOleh' => $idPengguna, 'DibatalkanPada' => now(), 'AlasanBatal' => $alasan, 'DiubahOleh' => $idPengguna])->save();

            $this->riwayat->Catat(PesananPembelian::JENIS_DOKUMEN, $terkunci->Id, $asal->value, $tujuan->value, $idPengguna, $tutup ? null : $alasan);
            $this->audit->Catat($tutup ? 'pesanan-pembelian.tutup' : 'pesanan-pembelian.batalkan', $terkunci, ['Status' => $asal->value], ['Status' => $tujuan->value, 'Alasan' => $tutup ? null : $alasan], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
