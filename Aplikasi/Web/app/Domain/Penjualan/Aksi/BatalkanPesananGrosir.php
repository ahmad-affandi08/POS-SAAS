<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Enum\StatusPesananGrosir;
use App\Domain\Penjualan\Model\PesananGrosir;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Batalkan SO grosir (F-12, §9.7). Hanya draf atau pesanan yang sudah dikonfirmasi **tetapi belum ada pengiriman**.
 *
 * Begitu barang berjalan, koreksinya lewat retur, bukan dengan membatalkan pesanan: penyerahan barang sudah membukukan
 * HPP, pendapatan, dan PPN (BR-12.2), dan itu tidak boleh hilang hanya karena pesanannya dibatalkan. Batasan ini
 * ditegakkan dua kali — status `SebagianDikirim`/`Selesai` memang tidak punya transisi ke `Dibatalkan`
 * (`StatusPesananGrosir::BisaBerubahKe`), dan di sini diberi pesan yang menjelaskan jalan keluarnya.
 *
 * Nomor dokumen yang sudah terpakai tetap terpakai (tidak didaur ulang), sesuai aturan nomor berurutan tanpa celah.
 */
final class BatalkanPesananGrosir
{
    public const PANJANG_ALASAN_MINIMAL = 5;

    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuidPesanan, int $idPengguna, string $alasan): PesananGrosir
    {
        $alasanBersih = trim($alasan);

        if (mb_strlen($alasanBersih) < self::PANJANG_ALASAN_MINIMAL) {
            throw new PelanggaranAturanBisnis(
                'AlasanBatalWajib',
                'Tulis alasan pembatalan minimal '.self::PANJANG_ALASAN_MINIMAL.' karakter.',
                'AlasanBatal',
            );
        }

        return DB::transaction(function () use ($uuidPesanan, $idPengguna, $alasanBersih): PesananGrosir {
            $pesanan = PesananGrosir::query()->where('Uuid', $uuidPesanan)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('PesananTidakDitemukan', 'Pesanan grosir tidak ditemukan.');

            if ($pesanan->Status === StatusPesananGrosir::Dibatalkan) {
                throw new PelanggaranAturanBisnis('PesananSudahDibatalkan', "Pesanan {$pesanan->Nomor} sudah dibatalkan.");
            }

            if (! $pesanan->Status->BisaBerubahKe(StatusPesananGrosir::Dibatalkan)) {
                throw new PelanggaranAturanBisnis(
                    'PesananSudahDikirim',
                    "Pesanan {$pesanan->Nomor} sudah {$pesanan->Status->AmbilLabel()} sehingga tidak bisa dibatalkan. Barang yang sudah diserahkan dikoreksi lewat retur.",
                );
            }

            $status = $pesanan->Status->value;
            $pesanan->UbahStatus(StatusPesananGrosir::Dibatalkan);
            $pesanan->fill([
                'DibatalkanOleh' => $idPengguna,
                'DibatalkanPada' => CarbonImmutable::now(),
                'AlasanBatal' => $alasanBersih,
                'DiubahOleh' => $idPengguna,
            ])->save();

            $this->riwayat->Catat(PesananGrosir::JENIS_DOKUMEN, $pesanan->Id, $status, $pesanan->Status->value, $idPengguna, $alasanBersih);
            $this->audit->Catat('grosir.pesanan-batalkan', $pesanan, nilaiLama: ['Status' => $status], nilaiBaru: [
                'Status' => $pesanan->Status->value,
                'Nomor' => $pesanan->Nomor,
                'AlasanBatal' => $alasanBersih,
            ]);

            return $pesanan;
        });
    }
}
