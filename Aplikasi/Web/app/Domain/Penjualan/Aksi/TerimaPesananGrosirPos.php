<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Data\DataBarisPesananGrosir;
use App\Domain\Penjualan\Data\DataPesananGrosir;
use App\Domain\Penjualan\Enum\SumberPesananGrosir;
use App\Domain\Penjualan\Model\KunjunganSales;
use App\Domain\Penjualan\Model\PesananGrosir;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Terima item outbox `PesananGrosir.Buat` (Modul Salesman bagian 1, §9.7, SLS-11): salesman mengambil pesanan grosir
 * di lapangan, bisa offline. Idempoten per Uuid (= `PesananGrosir.Uuid`).
 *
 * - **Harga dari server**: dokumen disusun `SimpanPesananGrosir` (price engine & tier pelanggan, snapshot di baris),
 *   jadi klien tidak pernah mengirim harga. Pesanan masuk sebagai **Draf**; konfirmasi + limit kredit BR-12.6 tetap di
 *   back-office, sehingga draf itu sendirilah titik tinjauannya.
 * - Ditolak hanya bentuk yang tidak mungkin benar: pengguna bukan anggota usaha, pelanggan/produk tidak dikenal, produk
 *   di luar cakupan grosir bagian 1 (resep/paket/jasa, batch/seri — pesan dari `SimpanPesananGrosir`), harga belum diatur.
 * - Pesanan yang sudah terjadi di lapangan tidak ditolak karena alasan lunak: salesman yang kehilangan izin
 *   `salesman.kunjungan` atau akses outlet setelah offline tetap diterima, alasannya dicatat di log audit
 *   (`Tinjauan`), dan operator back-office memutuskannya saat konfirmasi.
 * - Tanggal pesanan = tanggal bisnis outlet perangkat pada `DibuatPada`. Nomor `PG/...` diberikan server saat diterima
 *   (draf tidak punya nomor di perangkat; perangkat merujuknya lewat Uuid).
 * - `UuidKunjungan` (opsional): bila kunjungan itu sudah tercatat tanpa pesanan, pesanan ini ditautkan ke sana.
 */
final class TerimaPesananGrosirPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AnggotaOutlet $anggota,
        private readonly IdentitasPelanggan $identitas,
        private readonly InfoProdukStok $infoProduk,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly SimpanPesananGrosir $simpan,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<array{UuidProduk: string, UuidSatuan: string|null, Jumlah: Kuantitas}>  $baris
     */
    public function Jalankan(
        string $uuid,
        int $idPerangkat,
        int $idOutlet,
        string $uuidPelanggan,
        string $uuidPengguna,
        CarbonImmutable $dibuatPada,
        ?string $catatan,
        array $baris,
        ?string $uuidKunjungan,
    ): StatusItemSinkron {
        if ($dibuatPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu pesanan ada di masa depan. Periksa jam perangkat.', 'DibuatPada');
        }

        if (PesananGrosir::query()->where('Uuid', $uuid)->exists()) {
            return StatusItemSinkron::Duplikat;
        }

        $idTenant = $this->konteks->Wajib();
        [$salesman, $diOutlet] = $this->anggota->CariDiTenant($idTenant, $uuidPengguna, $idOutlet)
            ?? throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Salesman ini bukan anggota usaha ini.', 'UuidPengguna');

        if ($this->identitas->CariId($uuidPelanggan) === null) {
            throw new PelanggaranAturanBisnis('PelangganTidakDikenal', 'Pelanggan tidak ditemukan. Perbarui data pelanggan di aplikasi.', 'UuidPelanggan');
        }

        $produk = $this->infoProduk->AmbilDariUuid(array_values(array_unique(array_column($baris, 'UuidProduk'))));

        foreach ($baris as $b) {
            if (! isset($produk[$b['UuidProduk']])) {
                throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk pesanan tidak ditemukan. Perbarui katalog aplikasi.', 'Baris');
            }
        }

        $tinjauan = array_values(array_filter([
            $diOutlet ? null : "IzinBerubah: {$salesman->nama} tidak lagi terdaftar di outlet ini",
            $salesman->CekIzin(IzinTenant::SalesmanKunjungan->value) ? null : "IzinBerubah: {$salesman->nama} tidak punya izin salesman",
        ]));

        try {
            return DB::transaction(function () use ($uuid, $idPerangkat, $idOutlet, $uuidPelanggan, $dibuatPada, $catatan, $baris, $uuidKunjungan, $salesman, $tinjauan): StatusItemSinkron {
                $pesanan = $this->simpan->Jalankan(new DataPesananGrosir(
                    uuidPelanggan: $uuidPelanggan,
                    idOutlet: $idOutlet,
                    tanggal: $this->tanggalBisnis->Hitung($idOutlet, $dibuatPada),
                    baris: array_map(fn (array $b): DataBarisPesananGrosir => new DataBarisPesananGrosir($b['UuidProduk'], $b['UuidSatuan'], $b['Jumlah'], Uang::Nol()), $baris),
                    catatan: $catatan,
                    uuid: $uuid,
                    sumber: SumberPesananGrosir::Salesman,
                    idSalesman: $salesman->id,
                    idPerangkat: $idPerangkat,
                ), $salesman->id);

                if ($uuidKunjungan !== null) {
                    $kunjungan = KunjunganSales::query()->where('Uuid', $uuidKunjungan)->whereNull('IdPesananGrosir')->lockForUpdate()->first();
                    $kunjungan?->forceFill(['IdPesananGrosir' => $pesanan->Id])->save();
                }

                $this->audit->Catat('grosir.pesanan-salesman', $pesanan, nilaiBaru: [
                    'Nomor' => $pesanan->Nomor,
                    'IdSalesman' => $salesman->id,
                    'DibuatOfflinePada' => $dibuatPada->toIso8601ZuluString(),
                    'Tinjauan' => $tinjauan,
                ]);

                return StatusItemSinkron::Diterima;
            });
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) !== 1062) {
                throw $galat;
            }

            return StatusItemSinkron::Duplikat;
        }
    }
}
