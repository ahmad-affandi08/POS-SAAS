<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Kueri\PengaturanDepositTenant;
use App\Domain\Pelanggan\Layanan\PencatatDepositPenjualan;
use App\Domain\Penjualan\Data\DataIsiDepositPos;
use App\Domain\Penjualan\Enum\StatusIsiDeposit;
use App\Domain\Penjualan\Layanan\PenyusunJurnalPenjualan;
use App\Domain\Penjualan\Model\IsiDeposit;
use App\Domain\Penjualan\Model\MetodePembayaran;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `Deposit.Isi` (F-16d bagian 1, CRM-04): isi saldo deposit pelanggan di kasir (bisa offline).
 *
 * Urutan pemeriksaan: (1) Uuid sama → `Duplikat` bila Nomor & Jumlah sama, selain itu `UuidSudahDipakai`; (2) fitur paket
 * `pelanggan.deposit` (`FiturTidakAktif`); (3) shift milik perangkat (`ShiftTidakDitemukan`); (4) kasir anggota tenant
 * (`KasirTidakDitemukan`); (5) nomor `DEP/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}` unik (`NomorTidakValid`,
 * `NomorSudahDipakai`); (6) jumlah Rupiah bulat dalam batas `config/pelanggan.php` (`JumlahTidakValid`); (7) metode
 * tunai/QRIS/EDC/transfer/e-wallet (`MetodeBayarTidakDikenal`/`MetodeBayarBelumDidukung`).
 *
 * Uang sudah diterima kasir, jadi masalah berikut **tidak menolak** tetapi ditandai `PerluTinjauan`: pelanggan tidak
 * dikenal (`PelangganTidakDikenal`, saldo tidak ditambah), pelanggan diarsipkan (`PelangganDiarsipkan`, saldo tetap
 * ditambah), kasir kehilangan izin/outlet (`IzinBerubah`), shift sudah ditutup (`ShiftSudahDitutup`), periode terkunci
 * (`PeriodeTerkunci`, jurnal digeser).
 *
 * Efek di satu transaksi DB: dokumen `IsiDeposit` (`Diterima`), mutasi deposit `Isi` (+), jurnal **J-16.1** Dr akun
 * metode (seperti penjualan: tunai → Kas Outlet, QRIS/EDC/e-wallet → kliring, transfer → Bank), Cr Saldo Deposit
 * Pelanggan (dimensi outlet). Tanpa pendapatan, pajak, dan poin. Uangnya ikut kas shift (`RingkasanPenjualanShift`).
 */
final class TerimaIsiDepositPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    private const PANJANG_ALASAN_TINJAUAN = 500;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PengaturanDepositTenant $pengaturan,
        private readonly InfoShift $infoShift,
        private readonly OutletPenjualan $outletPenjualan,
        private readonly AnggotaOutlet $anggota,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly IdentitasPelanggan $identitasPelanggan,
        private readonly PencatatDepositPenjualan $deposit,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataIsiDepositPos $data): StatusItemSinkron
    {
        if ($data->dibuatPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu isi deposit ada di masa depan. Periksa jam perangkat.', 'DibuatPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) !== 1062) {
                throw $galat;
            }

            $lama = IsiDeposit::query()->where('Uuid', $data->uuid)->first();

            if ($lama !== null && $lama->Nomor === $data->nomor && Uang::Dari($lama->Jumlah)->SamaDengan($data->jumlah)) {
                return StatusItemSinkron::Duplikat;
            }

            if ($lama === null && str_contains($galat->getMessage(), 'UniqIsiDepositIdTenantNomor')) {
                throw self::GalatNomorDipakai($data->nomor);
            }

            throw self::GalatUuidDipakai();
        }
    }

    private function Proses(DataIsiDepositPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();

        // (1) Idempotensi per Uuid.
        $lama = IsiDeposit::query()->where('Uuid', $data->uuid)->lockForUpdate()->first();

        if ($lama !== null) {
            if ($lama->Nomor === $data->nomor && Uang::Dari($lama->Jumlah)->SamaDengan($data->jumlah)) {
                return StatusItemSinkron::Duplikat;
            }

            throw self::GalatUuidDipakai();
        }

        // (2) Fitur paket.
        if (! $this->pengaturan->CekBerlaku()) {
            throw new PelanggaranAturanBisnis('FiturTidakAktif', 'Paket usaha ini belum termasuk deposit pelanggan.', 'Jenis');
        }

        // (3) Shift perangkat (outbox FIFO mengirim shift lebih dulu).
        $shift = $this->infoShift->CariDiPerangkat($data->uuidShift, $data->idPerangkat)
            ?? throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Shift isi deposit ini tidak ditemukan di perangkat ini. Kirim data shift lebih dulu.', 'UuidShift');

        if ($data->dibuatPada->lessThan($shift->dibukaPada->subSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu isi deposit lebih awal dari waktu buka shift.', 'DibuatPada');
        }

        $outlet = $this->outletPenjualan->Ambil($shift->idOutlet, $data->idPerangkat)
            ?? throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Outlet shift isi deposit ini tidak ditemukan.', 'UuidShift');

        // (4) Kasir: bukan anggota tenant = ditolak; izin/outlet yang berubah setelah transaksi offline = tinjauan.
        [$kasir, $kasirDiOutlet] = $this->anggota->CariDiTenant($idTenant, $data->uuidPengguna, $outlet->idOutlet)
            ?? throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Kasir ini bukan anggota usaha ini.', 'UuidPengguna');
        $tinjauan = [];
        $izinBerubah = array_values(array_filter([
            $kasirDiOutlet ? null : "{$kasir->nama} tidak lagi terdaftar di outlet ini",
            $kasir->CekIzin(IzinTenant::PenjualanBuat->value) ? null : "{$kasir->nama} tidak lagi punya izin berjualan",
        ]));

        if ($izinBerubah !== []) {
            $tinjauan[] = 'IzinBerubah: '.implode('; ', $izinBerubah);
        }

        // (5) Nomor & periode.
        $tanggalBisnis = $this->tanggalBisnis->Hitung($outlet->idOutlet, $data->dibuatPada);
        $pola = '#^DEP/'.preg_quote($outlet->kodeOutlet, '#').'/(\d{6})/'.preg_quote($outlet->kodePerangkat, '#').'-\d{4,}$#';
        $tanggalBoleh = [$tanggalBisnis->format('ymd'), $data->dibuatPada->setTimezone($outlet->zonaWaktu)->format('ymd')];

        if (preg_match($pola, $data->nomor, $cocok) !== 1 || ! in_array($cocok[1], $tanggalBoleh, true)) {
            throw new PelanggaranAturanBisnis('NomorTidakValid', "Nomor {$data->nomor} tidak sesuai format DEP/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001.", 'Nomor');
        }

        if (IsiDeposit::query()->where('Nomor', $data->nomor)->exists()) {
            throw self::GalatNomorDipakai($data->nomor);
        }

        $pergeseranPeriode = $this->penjagaPeriode->JelaskanPergeseran($tanggalBisnis);

        if ($pergeseranPeriode !== null) {
            $tinjauan[] = $pergeseranPeriode;
        }

        // (6) Jumlah.
        $this->PeriksaJumlah($data->jumlah);

        // (7) Metode (yang dinonaktifkan setelah transaksi offline tetap diterima).
        $metode = MetodePembayaran::query()->where('Uuid', $data->uuidMetodePembayaran)->first()
            ?? throw new PelanggaranAturanBisnis('MetodeBayarTidakDikenal', 'Metode pembayaran tidak ditemukan.', 'UuidMetodePembayaran');

        if (! $metode->Jenis->CekBolehIsiDeposit()) {
            throw new PelanggaranAturanBisnis('MetodeBayarBelumDidukung', "Deposit tidak bisa diisi dengan {$metode->Jenis->AmbilLabel()}.", 'UuidMetodePembayaran');
        }

        // Pelanggan: tidak dikenal / diarsipkan tetap diterima (uang sudah diterima kasir).
        $idPelanggan = $this->identitasPelanggan->CariId($data->uuidPelanggan);

        if ($idPelanggan === null) {
            $tinjauan[] = 'PelangganTidakDikenal: pelanggan belum diterima server, saldo deposit belum ditambahkan';
        } elseif (! $this->identitasPelanggan->CekAktif($idPelanggan)) {
            $tinjauan[] = 'PelangganDiarsipkan: pelanggan sudah diarsipkan, saldo deposit tetap ditambahkan';
        }

        if (! $shift->aktif) {
            $tinjauan[] = 'ShiftSudahDitutup: isi deposit diterima setelah shift ditutup, belum masuk hitungan kas tutup shift';
        }

        $isi = IsiDeposit::query()->create([
            'Uuid' => $data->uuid,
            'IdOutlet' => $outlet->idOutlet,
            'IdPerangkat' => $data->idPerangkat,
            'IdShift' => $shift->id,
            'IdPelanggan' => $idPelanggan,
            'UuidPelanggan' => $data->uuidPelanggan,
            'IdPengguna' => $kasir->id,
            'Nomor' => $data->nomor,
            'DibuatOfflinePada' => $data->dibuatPada,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'IdMetodePembayaran' => $metode->Id,
            'JenisMetode' => $metode->Jenis,
            'NamaMetode' => mb_substr($metode->Nama, 0, 100),
            'Jumlah' => $data->jumlah->KeString(),
            'Referensi' => $data->referensi,
            'Status' => StatusIsiDeposit::Diterima,
            'PerluTinjauan' => $tinjauan !== [],
            'AlasanTinjauan' => $tinjauan === [] ? null : mb_substr(implode('; ', $tinjauan), 0, self::PANJANG_ALASAN_TINJAUAN),
            'DiterimaPada' => CarbonImmutable::now(),
        ]);

        if ($idPelanggan !== null) {
            $this->deposit->CatatIsi($idPelanggan, $isi->Id, $isi->Nomor, $data->jumlah, $tanggalBisnis, $kasir->id);
        }

        [$idAkun, $peran] = PenyusunJurnalPenjualan::TentukanAkunMetode($metode);
        $isi->IdJurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::IsiDeposit,
            idSumber: $isi->Id,
            uuidSumber: $isi->Uuid,
            nomorSumber: $isi->Nomor,
            tanggal: $tanggalBisnis,
            keterangan: mb_substr("Isi deposit {$isi->Nomor}", 0, 255),
            baris: [
                $idAkun !== null
                    ? new DataBarisJurnal(null, $idAkun, $outlet->idOutlet, $data->jumlah, Uang::Nol(), $metode->Nama)
                    : DataBarisJurnal::Debit($peran, $data->jumlah, $outlet->idOutlet, $metode->Nama),
                DataBarisJurnal::Kredit(PeranAkun::DepositPelanggan, $data->jumlah, $outlet->idOutlet, $isi->Nomor),
            ],
            idPengguna: $kasir->id,
        ))->idJurnal;
        $isi->save();

        $this->riwayat->Catat(IsiDeposit::JENIS_DOKUMEN, $isi->Id, null, StatusIsiDeposit::Diterima->value, $kasir->id);
        $this->audit->Catat('deposit.isi', $isi, nilaiBaru: [
            'Nomor' => $isi->Nomor,
            'Jumlah' => $isi->Jumlah,
            'Metode' => $metode->Nama,
            'PerluTinjauan' => $isi->PerluTinjauan,
        ], idPengguna: $kasir->id);

        return StatusItemSinkron::Diterima;
    }

    /** Rupiah bulat (tanpa sen), dalam batas `config/pelanggan.php`. */
    private function PeriksaJumlah(Uang $jumlah): void
    {
        $minimal = PengaturanDepositTenant::AmbilMinimalIsi();
        $maksimal = PengaturanDepositTenant::AmbilMaksimalIsi();

        if (! str_ends_with($jumlah->KeString(), '.00') || $jumlah->Bandingkan($minimal) < 0 || $jumlah->Bandingkan($maksimal) > 0) {
            throw new PelanggaranAturanBisnis(
                'JumlahTidakValid',
                "Isi deposit harus Rupiah bulat {$minimal->FormatRupiah()} sampai {$maksimal->FormatRupiah()}.",
                'Jumlah',
                422,
                ['MinimalIsi' => $minimal->KeString(), 'MaksimalIsi' => $maksimal->KeString()],
            );
        }
    }

    private static function GalatNomorDipakai(string $nomor): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$nomor} sudah dipakai isi deposit lain.", 'Nomor', 409);
    }

    private static function GalatUuidDipakai(): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik isi deposit ini sudah dipakai data lain. Buat ulang di aplikasi.', 'Uuid');
    }
}
