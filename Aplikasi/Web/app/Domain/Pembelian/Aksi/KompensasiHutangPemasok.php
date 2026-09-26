<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pembelian\Enum\StatusDokumenPembelian;
use App\Domain\Pembelian\Enum\StatusFakturPembelian;
use App\Domain\Pembelian\Layanan\PemrosesPenerimaanBarang;
use App\Domain\Pembelian\Layanan\PenomorPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PembayaranHutang;
use App\Domain\Pembelian\Model\PembayaranHutangAlokasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aksi publik domain Pembelian (F-16c bagian 4e): tagihan tenant ke pemasok (misal klaim promo) diselesaikan dengan
 * memotong hutang ke pemasok yang sama (kompensasi/nota debit). Dialokasikan ke faktur terbuka pemasok itu mulai yang
 * paling lama (jatuh tempo, lalu tanggal), bukan belanja stok; total harus ≤ sisa hutang. Hasilnya dokumen
 * `PembayaranHutang` bertanda `Kompensasi` (tanpa kas, tidak bisa dibatalkan) dengan jurnal Dr Hutang Usaha per outlet
 * faktur, Cr baris kredit dari pemanggil (akun tagihannya). Audit `pembayaran-hutang.kompensasi`.
 */
final class KompensasiHutangPemasok
{
    public function __construct(
        private readonly PemrosesPenerimaanBarang $pemroses,
        private readonly PenomorPembelian $penomor,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<DataBarisJurnal>  $barisKredit  sisi kredit (jumlahnya = total yang dipotong)
     * @return array{Id: int, Nomor: string, IdJurnal: int}
     *
     * @throws PelanggaranAturanBisnis PemasokTidakDikenal, HutangTidakCukup
     */
    public function Jalankan(int $idPemasok, CarbonImmutable $tanggal, int $idAkunTagihan, array $barisKredit, string $catatan, int $idPengguna): array
    {
        return DB::transaction(function () use ($idPemasok, $tanggal, $idAkunTagihan, $barisKredit, $catatan, $idPengguna): array {
            $pemasok = Pemasok::query()->withTrashed()->whereKey($idPemasok)->first()
                ?? throw new PelanggaranAturanBisnis('PemasokTidakDikenal', 'Pemasok tidak ditemukan.', 'UuidPemasok');
            $total = array_reduce($barisKredit, fn (Uang $t, DataBarisJurnal $b): Uang => $t->Tambah($b->kredit), Uang::Nol());
            $faktur = $this->KueriFakturTerbuka($idPemasok)->lockForUpdate()->get();
            $sisaHutang = $faktur->reduce(fn (Uang $t, FakturPembelian $f): Uang => $t->Tambah($f->AmbilSisa()), Uang::Nol());

            if ($total->Bandingkan(Uang::Nol()) <= 0 || $total->Bandingkan($sisaHutang) > 0) {
                throw new PelanggaranAturanBisnis('HutangTidakCukup', "Sisa hutang ke {$pemasok->Nama} {$sisaHutang->FormatRupiah()}, kurang dari yang akan dipotong {$total->FormatRupiah()}. Catat sebagai penerimaan kas/bank.", 'Cara');
            }

            $alokasi = [];
            $sisa = $total;

            foreach ($faktur as $f) {
                if ($sisa->Bandingkan(Uang::Nol()) <= 0) {
                    break;
                }

                $bagian = $f->AmbilSisa()->Bandingkan($sisa) < 0 ? $f->AmbilSisa() : $sisa;

                if ($bagian->Bandingkan(Uang::Nol()) > 0) {
                    $alokasi[] = [$f, $bagian];
                    $sisa = $sisa->Kurangi($bagian);
                }
            }

            $idOutlet = array_values(array_unique(array_map(fn (array $a): ?int => $a[0]->IdOutlet, $alokasi)));
            $outlet = count($idOutlet) === 1 ? $idOutlet[0] : null;
            $this->pemroses->PastikanTanggal($tanggal, $outlet);

            $pembayaran = PembayaranHutang::query()->create([
                'Nomor' => $this->penomor->AmbilNomorTenant(JenisDokumenBernomor::PembayaranHutang, $tanggal),
                'IdPemasok' => $pemasok->Id,
                'IdAkun' => $idAkunTagihan,
                'IdOutlet' => $outlet,
                'Tanggal' => $tanggal->toDateString(),
                'Jumlah' => $total->KeString(),
                'Status' => StatusDokumenPembelian::Diposting,
                'Kompensasi' => true,
                'Catatan' => mb_substr($catatan, 0, 500),
                'DibuatOleh' => $idPengguna,
            ]);
            $baris = [];

            foreach ($alokasi as [$f, $jumlah]) {
                PembayaranHutangAlokasi::query()->create(['IdPembayaranHutang' => $pembayaran->Id, 'IdFakturPembelian' => $f->Id, 'Jumlah' => $jumlah->KeString()]);
                $asal = $f->Status;
                $f->JumlahDibayar = Uang::Dari($f->JumlahDibayar)->Tambah($jumlah)->KeString();
                $f->SelaraskanStatus();
                $f->save();

                if ($asal !== $f->Status) {
                    $this->riwayat->Catat(FakturPembelian::JENIS_DOKUMEN, $f->Id, $asal->value, $f->Status->value, $idPengguna);
                }

                $baris[] = DataBarisJurnal::Debit(PeranAkun::HutangUsaha, $jumlah, $f->IdOutlet, $f->Nomor);
            }

            $jurnal = $this->postingJurnal->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::PembayaranHutang,
                idSumber: $pembayaran->Id,
                uuidSumber: $pembayaran->Uuid,
                nomorSumber: $pembayaran->Nomor,
                tanggal: $tanggal,
                keterangan: mb_substr("Potong hutang {$pembayaran->Nomor} ke {$pemasok->Nama}: {$catatan}", 0, 255),
                baris: [...$baris, ...$barisKredit],
                idPengguna: $idPengguna,
            ));
            $pembayaran->IdJurnal = $jurnal->idJurnal;
            $pembayaran->save();

            $this->riwayat->Catat(PembayaranHutang::JENIS_DOKUMEN, $pembayaran->Id, null, StatusDokumenPembelian::Diposting->value, $idPengguna);
            $this->audit->Catat('pembayaran-hutang.kompensasi', $pembayaran, nilaiBaru: [
                'Nomor' => $pembayaran->Nomor,
                'Pemasok' => $pemasok->Nama,
                'Jumlah' => $pembayaran->Jumlah,
                'Faktur' => array_map(fn (array $a): string => $a[0]->Nomor, $alokasi),
                'Catatan' => $catatan,
            ], idPengguna: $idPengguna);

            return ['Id' => $pembayaran->Id, 'Nomor' => $pembayaran->Nomor, 'IdJurnal' => $jurnal->idJurnal];
        });
    }

    /** @return Builder<FakturPembelian> */
    private function KueriFakturTerbuka(int $idPemasok): Builder
    {
        return FakturPembelian::query()
            ->where('IdPemasok', $idPemasok)
            ->where('BelanjaStok', false)
            ->whereIn('Status', [StatusFakturPembelian::BelumDibayar->value, StatusFakturPembelian::DibayarSebagian->value])
            ->orderBy('JatuhTempo')
            ->orderBy('Tanggal')
            ->orderBy('Id');
    }
}
