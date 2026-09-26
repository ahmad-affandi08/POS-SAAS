<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Kueri\DefinisiPaketSesi;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Data\DataPemakaianSesiPos;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\StatusPemakaianSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Kueri\PengaturanSesiTenant;
use App\Domain\Pelanggan\Layanan\BukuSesi;
use App\Domain\Pelanggan\Model\PemakaianSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Terima item outbox `Sesi.Pakai` (F-16d bagian 2, CRM-04, J-16.3): pelanggan memakai sesi paket di kasir (bisa
 * offline). Idempoten per Uuid dokumen. Pemakaian sudah terjadi di kasir, jadi hanya bentuk yang tidak mungkin benar
 * yang ditolak (saldo/produk/pengguna tidak dikenal, fitur tidak aktif); saldo kurang, sudah tidak aktif, lewat masa
 * berlaku, atau produk di luar paket = diterima + `PerluTinjauan`.
 *
 * Nilai diakui = `NilaiTersisa × n ÷ SisaSesi` (sesi terakhir mengakui seluruh sisa); jurnal Dr Pendapatan Diterima
 * Dimuka (dimensi outlet penjualan paket), Cr Pendapatan Jasa (dimensi outlet pemakaian), di transaksi DB yang sama.
 */
final class TerimaPemakaianSesiPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    private const PANJANG_ALASAN_TINJAUAN = 500;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PengaturanSesiTenant $pengaturan,
        private readonly AnggotaOutlet $anggota,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly DefinisiPaketSesi $definisi,
        private readonly BukuSesi $buku,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPemakaianSesiPos $data): StatusItemSinkron
    {
        if ($data->dibuatPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu pemakaian sesi ada di masa depan. Periksa jam perangkat.', 'DibuatPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) !== 1062) {
                throw $galat;
            }

            return $this->CekDuplikat(PemakaianSesi::query()->where('Uuid', $data->uuid)->first(), $data);
        }
    }

    private function Proses(DataPemakaianSesiPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();

        // (1) Idempotensi per Uuid.
        $lama = PemakaianSesi::query()->where('Uuid', $data->uuid)->lockForUpdate()->first();

        if ($lama !== null) {
            return $this->CekDuplikat($lama, $data);
        }

        // (2) Fitur paket langganan.
        if (! $this->pengaturan->CekBerlaku()) {
            throw new PelanggaranAturanBisnis('FiturTidakAktif', 'Paket usaha ini belum termasuk paket sesi.', 'Jenis');
        }

        // (3) Pengguna: bukan anggota tenant = ditolak; izin/outlet yang berubah setelah offline = tinjauan.
        [$kasir, $diOutlet] = $this->anggota->CariDiTenant($idTenant, $data->uuidPengguna, $data->idOutlet)
            ?? throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Kasir ini bukan anggota usaha ini.', 'UuidPengguna');
        $tinjauan = array_values(array_filter([
            $diOutlet ? null : "IzinBerubah: {$kasir->nama} tidak lagi terdaftar di outlet ini",
            $kasir->CekIzin(IzinTenant::PenjualanBuat->value) ? null : "IzinBerubah: {$kasir->nama} tidak lagi punya izin berjualan",
        ]));

        // (4) Saldo & produk.
        $idSaldo = SaldoSesi::query()->where('Uuid', $data->uuidSaldoSesi)->value('Id');
        $saldo = $idSaldo === null ? null : $this->buku->Kunci((int) $idSaldo);

        if ($saldo === null) {
            throw new PelanggaranAturanBisnis('SaldoSesiTidakDikenal', 'Paket sesi pelanggan ini tidak ditemukan.', 'UuidSaldoSesi', 404);
        }

        $idProduk = $this->definisi->CariIdProduk($data->uuidProduk)
            ?? throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk yang dikerjakan tidak ditemukan.', 'UuidProduk');
        $namaProduk = $this->definisi->AmbilNamaProduk($idProduk);

        if (! $this->definisi->CekProdukBerlaku($saldo->IdPaketSesi, $idProduk)) {
            $tinjauan[] = "ProdukDiluarPaket: {$namaProduk} tidak termasuk paket {$saldo->NamaPaket}";
        }

        // (5) Tanggal bisnis & periode.
        $tanggalBisnis = $this->tanggalBisnis->Hitung($data->idOutlet, $data->dibuatPada);
        $pergeseran = $this->penjagaPeriode->JelaskanPergeseran($tanggalBisnis);

        if ($pergeseran !== null) {
            $tinjauan[] = $pergeseran;
        }

        // (6) Sisa sesi: yang bisa dipotong = min(jumlah, sisa) bila saldo masih aktif.
        $dipotong = $saldo->Status === StatusSaldoSesi::Aktif ? min($data->jumlah, $saldo->SisaSesi) : 0;

        if ($saldo->Status !== StatusSaldoSesi::Aktif) {
            $tinjauan[] = "SesiTidakAktif: paket {$saldo->NamaPaket} berstatus {$saldo->Status->AmbilLabel()}, sesi tidak dipotong";
        } elseif ($dipotong < $data->jumlah) {
            $tinjauan[] = "SesiKurang: sisa {$saldo->SisaSesi} sesi, dipakai {$data->jumlah}";
        }

        if ($saldo->BerlakuSampai !== null && $tanggalBisnis->toDateString() > $saldo->BerlakuSampai->toDateString()) {
            $tinjauan[] = "SesiLewatMasaBerlaku: paket berlaku sampai {$saldo->BerlakuSampai->toDateString()}";
        }

        $nilai = $this->buku->HitungNilaiPakai($saldo, $dipotong);
        $pemakaian = PemakaianSesi::query()->create([
            'Uuid' => $data->uuid,
            'IdOutlet' => $data->idOutlet,
            'IdPerangkat' => $data->idPerangkat,
            'IdSaldoSesi' => $saldo->Id,
            'UuidSaldoSesi' => $data->uuidSaldoSesi,
            'IdPelanggan' => $saldo->IdPelanggan,
            'IdProduk' => $idProduk,
            'NamaProduk' => $namaProduk === null ? null : mb_substr($namaProduk, 0, 200),
            'Jumlah' => $data->jumlah,
            'IdPengguna' => $kasir->id,
            'DibuatOfflinePada' => $data->dibuatPada,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'NilaiDiakui' => $nilai->KeString(),
            'Status' => StatusPemakaianSesi::Diterima,
            'PerluTinjauan' => $tinjauan !== [],
            'AlasanTinjauan' => $tinjauan === [] ? null : mb_substr(implode('; ', $tinjauan), 0, self::PANJANG_ALASAN_TINJAUAN),
            'DiterimaPada' => CarbonImmutable::now(),
        ]);

        if ($dipotong > 0) {
            $mutasi = $this->buku->Catat($saldo, JenisMutasiSesi::Pakai, -$dipotong, Uang::Nol()->Kurangi($nilai), SumberMutasiSesi::PemakaianSesi, $pemakaian->Id, $saldo->NomorPenjualan, $tanggalBisnis, $namaProduk, $kasir->id);

            if ($nilai->Bandingkan(Uang::Nol()) > 0) {
                $pemakaian->IdJurnal = $this->postingJurnal->Jalankan(new DataJurnal(
                    jenisSumber: JenisSumberJurnal::PemakaianSesi,
                    idSumber: $pemakaian->Id,
                    uuidSumber: $pemakaian->Uuid,
                    nomorSumber: $saldo->NomorPenjualan,
                    tanggal: $tanggalBisnis,
                    keterangan: mb_substr("Pemakaian {$dipotong} sesi {$saldo->NamaPaket}", 0, 255),
                    baris: [
                        DataBarisJurnal::Debit(PeranAkun::PendapatanDiterimaDimuka, $nilai, $saldo->IdOutlet),
                        DataBarisJurnal::Kredit(PeranAkun::PendapatanJasa, $nilai, $data->idOutlet),
                    ],
                    idPengguna: $kasir->id,
                ))->idJurnal;
                $pemakaian->save();
                $mutasi->IdJurnal = $pemakaian->IdJurnal;
                $mutasi->save();
            }
        }

        $this->audit->Catat('pelanggan.sesi-pakai', $pemakaian, nilaiBaru: [
            'Paket' => $saldo->NamaPaket,
            'Jumlah' => $data->jumlah,
            'SisaSesi' => $saldo->SisaSesi,
            'NilaiDiakui' => $nilai->KeString(),
            'PerluTinjauan' => $pemakaian->PerluTinjauan,
        ], idPengguna: $kasir->id);

        return StatusItemSinkron::Diterima;
    }

    private function CekDuplikat(?PemakaianSesi $lama, DataPemakaianSesiPos $data): StatusItemSinkron
    {
        if ($lama !== null && $lama->UuidSaldoSesi === $data->uuidSaldoSesi && $lama->Jumlah === $data->jumlah) {
            return StatusItemSinkron::Duplikat;
        }

        throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik pemakaian sesi ini sudah dipakai data lain. Ulangi dari aplikasi.', 'Uuid');
    }
}
