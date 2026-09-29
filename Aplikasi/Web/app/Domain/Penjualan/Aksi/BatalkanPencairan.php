<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Layanan\PenyusunJurnalPencairan;
use App\Domain\Penjualan\Model\Pencairan;
use App\Domain\Penjualan\Model\PencairanDetail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan pencairan (F-08, J-08.1 pembalik). Dokumennya tidak diedit dan tidak dihapus (aturan #8): seluruh baris
 * J-08.1 dibalik sisinya, sehingga uangnya keluar lagi dari akun kas/bank, potongannya dicabut dari beban, dan akun
 * kliringnya kembali menanggung nilai transaksinya.
 *
 * Pembayaran yang tadi dicairkan **dilepas** dengan mengosongkan `PencairanDetail.IdPembayaranAktif`, jadi bisa
 * dicairkan ulang di dokumen yang benar. Barisnya sendiri tetap utuh — nomor penjualan, tanggal, dan jumlahnya masih
 * terbaca, karena dokumen yang dibatalkan tetap harus bisa dijelaskan. Cermin `SuratJalan.IdFakturPenjualan` yang juga
 * dilepas saat fakturnya dibatalkan.
 *
 * Idempoten: pencairan yang sudah `Dibatalkan` dikembalikan apa adanya tanpa jurnal kedua.
 */
final class BatalkanPencairan
{
    public const PANJANG_ALASAN_MINIMAL = 5;

    public function __construct(
        private readonly PenyusunJurnalPencairan $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AlasanBatalWajib, PencairanTidakDitemukan
     */
    public function Jalankan(string $uuidPencairan, string $alasan, int $idPengguna): Pencairan
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::PANJANG_ALASAN_MINIMAL || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis(
                'AlasanBatalWajib',
                'Alasan pembatalan wajib diisi, '.self::PANJANG_ALASAN_MINIMAL.' sampai 255 karakter.',
                'Alasan',
            );
        }

        return DB::transaction(
            fn (): Pencairan => $this->Batalkan($uuidPencairan, $alasan, $idPengguna),
            max(1, (int) config('persediaan.PercobaanTransaksi', 3)),
        );
    }

    private function Batalkan(string $uuid, string $alasan, int $idPengguna): Pencairan
    {
        $pencairan = Pencairan::query()->where('Uuid', $uuid)->lockForUpdate()->first()
            ?? throw new PelanggaranAturanBisnis('PencairanTidakDitemukan', 'Pencairan tidak ditemukan.');

        if ($pencairan->Status === StatusDokumenTerposting::Dibatalkan) {
            return $pencairan;
        }

        $tanggal = CarbonImmutable::parse($pencairan->Tanggal->format('Y-m-d'));
        $jurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::Pencairan,
            idSumber: $pencairan->Id,
            uuidSumber: $pencairan->Uuid,
            nomorSumber: $pencairan->Nomor,
            tanggal: $tanggal,
            keterangan: mb_substr("Pembatalan pencairan {$pencairan->Nomor}", 0, 255),
            baris: $this->penyusunJurnal->Baris(
                $pencairan->AmbilJumlahKotor(),
                $pencairan->AmbilJumlahBersih(),
                $pencairan->AmbilBiaya(),
                $pencairan->IdAkunTujuan,
                $pencairan->IdAkunKliring,
                $pencairan->IdOutlet,
                pembalik: true,
            ),
            idPengguna: $idPengguna,
            kunciSumber: 'Pembatalan',
            idJurnalDibalik: $pencairan->IdJurnal,
        ));

        // Melepas klaim atas pembayarannya; `IdPenjualanPembayaran` di baris yang sama tetap utuh untuk dibaca.
        PencairanDetail::query()->where('IdPencairan', $pencairan->Id)->update(['IdPembayaranAktif' => null]);

        $asal = $pencairan->Status;
        $pencairan->UbahStatus(StatusDokumenTerposting::Dibatalkan);
        $pencairan->IdJurnalPembatalan = $jurnal->idJurnal;
        $pencairan->DibatalkanOleh = $idPengguna;
        $pencairan->DibatalkanPada = now();
        $pencairan->AlasanBatal = mb_substr($alasan, 0, 255);
        $pencairan->DiubahOleh = $idPengguna;
        $pencairan->save();

        $this->riwayat->Catat(Pencairan::JENIS_DOKUMEN, $pencairan->Id, $asal->value, $pencairan->Status->value, $idPengguna, $alasan);
        $this->audit->Catat('pencairan.batalkan', $pencairan, nilaiBaru: [
            'Nomor' => $pencairan->Nomor,
            'Alasan' => $alasan,
            'NomorJurnalPembatalan' => $jurnal->nomor,
        ], idPengguna: $idPengguna);

        return $pencairan;
    }
}
