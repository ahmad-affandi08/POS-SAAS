<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\PembayaranHutang;
use App\Domain\Pembelian\Model\PembayaranHutangAlokasi;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan pembayaran hutang (F-04 fase 1, CLAUDE.md #8): jurnal J-04.4 dibalik (cermin, `KunciSumber =
 * Pembatalan`) bertanggal hari bisnis, sisa faktur bertambah kembali & statusnya diselaraskan. Pelunasan belanja stok
 * hanya dibatalkan lewat pembatalan belanjanya. Alasan 5–255 karakter. Audit `pembayaran-hutang.batalkan`.
 *
 * Urutan kunci: faktur (urut Id) → pembayaran.
 */
final class BatalkanPembayaranHutang
{
    public function __construct(
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly BalikkanJurnal $balikkan,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AlasanTidakValid, BagianBelanjaStok
     */
    public function Jalankan(PembayaranHutang $pembayaran, string $alasan, int $idPengguna): PembayaranHutang
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan pembatalan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($pembayaran, $alasan, $idPengguna): PembayaranHutang {
            $alokasi = PembayaranHutangAlokasi::query()->where('IdPembayaranHutang', $pembayaran->Id)->get();
            $faktur = FakturPembelian::query()->whereIn('Id', $alokasi->pluck('IdFakturPembelian')->all())->orderBy('Id')->lockForUpdate()->get()->keyBy('Id');
            $terkunci = PembayaranHutang::query()->whereKey($pembayaran->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === StatusDokumenPembelian::Dibatalkan) {
                return $terkunci;
            }

            if ($terkunci->BelanjaStok) {
                throw new PelanggaranAturanBisnis('BagianBelanjaStok', 'Pembayaran ini bagian dari belanja stok. Batalkan belanja stoknya dari halaman penerimaan barang.');
            }

            foreach ($alokasi as $a) {
                /** @var FakturPembelian $f */
                $f = $faktur->get($a->IdFakturPembelian);
                $asal = $f->Status;
                $f->JumlahDibayar = Uang::Dari($f->JumlahDibayar)->Kurangi(Uang::Dari($a->Jumlah))->KeString();
                $f->SelaraskanStatus();
                $f->save();

                if ($asal !== $f->Status) {
                    $this->riwayat->Catat(FakturPembelian::JENIS_DOKUMEN, $f->Id, $asal->value, $f->Status->value, $idPengguna, $alasan);
                }
            }

            $jurnal = $terkunci->IdJurnal === null ? null : $this->balikkan->Jalankan(
                $terkunci->IdJurnal,
                $this->tanggalBisnis->Hitung($terkunci->IdOutlet),
                mb_substr("Pembatalan pembayaran hutang {$terkunci->Nomor}", 0, 255),
                JenisSumberJurnal::PembayaranHutang,
                $terkunci->Id,
                'Pembatalan',
                $idPengguna,
            );

            $terkunci->UbahStatus(StatusDokumenPembelian::Dibatalkan);
            $terkunci->fill(['IdJurnalPembatalan' => $jurnal?->idJurnal, 'AlasanBatal' => $alasan, 'DibatalkanOleh' => $idPengguna, 'DibatalkanPada' => now()])->save();

            $this->riwayat->Catat(PembayaranHutang::JENIS_DOKUMEN, $terkunci->Id, StatusDokumenPembelian::Diposting->value, StatusDokumenPembelian::Dibatalkan->value, $idPengguna, $alasan);
            $this->audit->Catat('pembayaran-hutang.batalkan', $terkunci, ['Status' => StatusDokumenPembelian::Diposting->value], [
                'Status' => StatusDokumenPembelian::Dibatalkan->value,
                'Nomor' => $terkunci->Nomor,
                'Alasan' => $alasan,
                'NomorJurnalPembatalan' => $jurnal?->nomor,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
