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
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Data\DataPesananPenjualanPos;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use App\Domain\Penjualan\Layanan\PenyusunJurnalPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\PesananPenjualan;
use App\Domain\Penjualan\Model\PesananPenjualanDetail;
use App\Domain\Penjualan\Model\PesananPenjualanPembayaran;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananPenjualan.Buat` (F-12 bagian 2, SLS-02): pre-order + uang muka dari perangkat (bisa offline).
 * Idempoten per Uuid (`Duplikat` bila Nomor & UangMuka sama). Pemeriksaan: shift milik perangkat, kasir anggota outlet
 * ber-izin `penjualan.buat`, pelanggan dikenal, nomor `SO/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}` unik, periode
 * terbuka, produk dikenal, tanggal ambil ≥ tanggal pesan, metode DP tunai/QRIS statis/EDC/transfer/e-wallet, DP > 0 dan
 * ≤ total pesanan. Jurnal J-07.3 di transaksi yang sama: Dr akun metode (seperti penjualan), Cr Uang Muka Pelanggan
 * (dimensi outlet). Tanpa stok & pendapatan. Metode sistem "Uang muka (DP)" disiapkan untuk pengambilan.
 */
final class TerimaPesananPenjualanPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoShift $infoShift,
        private readonly OutletPenjualan $outletPenjualan,
        private readonly AnggotaOutlet $anggota,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly KomposisiPenjualan $komposisi,
        private readonly IdentitasPelanggan $identitasPelanggan,
        private readonly SiapkanMetodeUangMuka $metodeUangMuka,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPesananPenjualanPos $data): StatusItemSinkron
    {
        if ($data->dipesanPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu pesanan ada di masa depan. Periksa jam perangkat.', 'DipesanPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) !== 1062) {
                throw $galat;
            }

            $lama = PesananPenjualan::query()->where('Uuid', $data->uuid)->first();

            if ($lama !== null && $lama->Nomor === $data->nomor) {
                return StatusItemSinkron::Duplikat;
            }

            if ($lama === null && str_contains($galat->getMessage(), 'UniqPesananPenjualanIdTenantNomor')) {
                throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$data->nomor} sudah dipakai pesanan lain.", 'Nomor', 409);
            }

            throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik pesanan ini sudah dipakai data lain. Buat ulang pesanan di aplikasi.', 'Uuid');
        }
    }

    private function Proses(DataPesananPenjualanPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();
        $lama = PesananPenjualan::query()->where('Uuid', $data->uuid)->lockForUpdate()->first();

        if ($lama !== null) {
            if ($lama->Nomor === $data->nomor && Uang::Dari($lama->UangMuka)->SamaDengan(self::HitungUangMuka($data))) {
                return StatusItemSinkron::Duplikat;
            }

            throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik pesanan ini sudah dipakai data lain. Buat ulang pesanan di aplikasi.', 'Uuid');
        }

        $shift = $this->infoShift->CariDiPerangkat($data->uuidShift, $data->idPerangkat);

        if ($shift === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Shift pesanan ini tidak ditemukan di perangkat ini. Kirim data shift lebih dulu.', 'UuidShift');
        }

        $outlet = $this->outletPenjualan->Ambil($shift->idOutlet, $data->idPerangkat)
            ?? throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Outlet shift pesanan ini tidak ditemukan.', 'UuidShift');
        $kasir = $this->anggota->Cari($idTenant, $data->uuidPengguna, $outlet->idOutlet);

        if ($kasir === null || ! $kasir->CekIzin(IzinTenant::PenjualanBuat->value)) {
            throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Kasir ini tidak terdaftar di outlet atau tidak punya izin berjualan.', 'UuidPengguna');
        }

        $idPelanggan = $this->identitasPelanggan->CariId($data->uuidPelanggan)
            ?? throw new PelanggaranAturanBisnis('PelangganTidakDikenal', 'Pelanggan pre-order belum diterima server. Kirim data pelanggan lebih dulu.', 'UuidPelanggan');

        $tanggalBisnis = $this->tanggalBisnis->Hitung($outlet->idOutlet, $data->dipesanPada);
        $pola = '#^SO/'.preg_quote($outlet->kodeOutlet, '#').'/(\d{6})/'.preg_quote($outlet->kodePerangkat, '#').'-\d{4,}$#';
        $tanggalBoleh = [$tanggalBisnis->format('ymd'), $data->dipesanPada->setTimezone($outlet->zonaWaktu)->format('ymd')];

        if (preg_match($pola, $data->nomor, $cocok) !== 1 || ! in_array($cocok[1], $tanggalBoleh, true)) {
            throw new PelanggaranAturanBisnis('NomorTidakValid', "Nomor {$data->nomor} tidak sesuai format SO/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001.", 'Nomor');
        }

        if (PesananPenjualan::query()->where('Nomor', $data->nomor)->exists()) {
            throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$data->nomor} sudah dipakai pesanan lain.", 'Nomor', 409);
        }

        $this->penjagaPeriode->PastikanTerbuka($tanggalBisnis);

        if ($data->tanggalAmbil->toDateString() < $tanggalBisnis->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalAmbilTidakValid', 'Tanggal ambil tidak boleh sebelum tanggal pesan.', 'TanggalAmbil');
        }

        $produk = $this->komposisi->AmbilProduk(array_values(array_map(fn ($b): string => $b->uuidProduk, $data->baris)));

        foreach ($data->baris as $indeks => $baris) {
            if (! isset($produk[$baris->uuidProduk])) {
                throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk pada baris ke-'.($indeks + 1).' tidak ditemukan.', "Baris.{$indeks}.UuidProduk");
            }
        }

        $metode = MetodePembayaran::query()->whereIn('Uuid', array_map(fn ($p): string => $p->uuidMetodePembayaran, $data->pembayaran))->get()->keyBy('Uuid');
        $uangMuka = self::HitungUangMuka($data);

        foreach ($data->pembayaran as $indeks => $bayar) {
            $m = $metode->get($bayar->uuidMetodePembayaran);

            if (! $m instanceof MetodePembayaran) {
                throw new PelanggaranAturanBisnis('MetodeBayarTidakDikenal', 'Metode pembayaran tidak ditemukan.', "Pembayaran.{$indeks}.UuidMetodePembayaran");
            }

            if (! $m->Jenis->CekBolehUangMuka()) {
                throw new PelanggaranAturanBisnis('MetodeBayarBelumDidukung', "Uang muka tidak bisa dibayar dengan {$m->Jenis->AmbilLabel()}.", "Pembayaran.{$indeks}.UuidMetodePembayaran");
            }
        }

        if ($uangMuka->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('UangMukaWajib', 'Uang muka pre-order harus lebih dari Rp 0.', 'Pembayaran');
        }

        if ($uangMuka->Bandingkan($data->totalPesanan) > 0) {
            throw new PelanggaranAturanBisnis('UangMukaMelebihiTotal', "Uang muka {$uangMuka->FormatRupiah()} melebihi total pesanan {$data->totalPesanan->FormatRupiah()}.", 'Pembayaran');
        }

        $this->metodeUangMuka->Jalankan($kasir->id);

        $pesanan = PesananPenjualan::query()->create([
            'Uuid' => $data->uuid,
            'IdOutlet' => $outlet->idOutlet,
            'IdPerangkat' => $data->idPerangkat,
            'IdShift' => $shift->id,
            'IdPelanggan' => $idPelanggan,
            'IdPengguna' => $kasir->id,
            'Nomor' => $data->nomor,
            'DipesanPada' => $data->dipesanPada,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'TanggalAmbil' => $data->tanggalAmbil->toDateString(),
            'Catatan' => $data->catatan,
            'Status' => StatusPesananPenjualan::Dipesan,
            'TotalPesanan' => $data->totalPesanan->KeString(),
            'UangMuka' => $uangMuka->KeString(),
        ]);

        foreach ($data->baris as $baris) {
            PesananPenjualanDetail::query()->create([
                'Uuid' => $baris->uuid,
                'IdPesananPenjualan' => $pesanan->Id,
                'IdProduk' => $produk[$baris->uuidProduk]->id,
                'UuidProduk' => $produk[$baris->uuidProduk]->uuid,
                'UuidProdukSatuan' => $baris->uuidProdukSatuan,
                'NamaProduk' => mb_substr($produk[$baris->uuidProduk]->nama, 0, 200),
                'Jumlah' => Kuantitas::Dari($baris->jumlah)->KeString(),
                'HargaSatuan' => Uang::Dari($baris->hargaSatuan)->KeString(),
                'HargaPilihan' => Uang::Dari($baris->hargaPilihan)->KeString(),
                'Pilihan' => $baris->pilihan === [] ? null : $baris->pilihan,
                'Catatan' => $baris->catatan,
            ]);
        }

        $barisJurnal = [];

        foreach ($data->pembayaran as $bayar) {
            /** @var MetodePembayaran $m */
            $m = $metode->get($bayar->uuidMetodePembayaran);
            PesananPenjualanPembayaran::query()->create([
                'Uuid' => $bayar->uuid,
                'IdPesananPenjualan' => $pesanan->Id,
                'IdMetodePembayaran' => $m->Id,
                'JenisMetode' => $m->Jenis,
                'Jumlah' => $bayar->jumlah->KeString(),
                'Referensi' => $bayar->referensi,
            ]);
            [$idAkun, $peran] = PenyusunJurnalPenjualan::TentukanAkunMetode($m);
            $barisJurnal[] = $idAkun !== null
                ? new DataBarisJurnal(null, $idAkun, $outlet->idOutlet, $bayar->jumlah, Uang::Nol(), $m->Nama)
                : DataBarisJurnal::Debit($peran, $bayar->jumlah, $outlet->idOutlet, $m->Nama);
        }

        $barisJurnal[] = DataBarisJurnal::Kredit(PeranAkun::UangMukaPelanggan, $uangMuka, $outlet->idOutlet, $pesanan->Nomor);
        $pesanan->IdJurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::PesananPenjualan,
            idSumber: $pesanan->Id,
            uuidSumber: $pesanan->Uuid,
            nomorSumber: $pesanan->Nomor,
            tanggal: $tanggalBisnis,
            keterangan: mb_substr("Uang muka pre-order {$pesanan->Nomor}", 0, 255),
            baris: $barisJurnal,
            idPengguna: $kasir->id,
        ))->idJurnal;
        $pesanan->save();

        $this->riwayat->Catat(PesananPenjualan::JENIS_DOKUMEN, $pesanan->Id, null, StatusPesananPenjualan::Dipesan->value, $kasir->id);
        $this->audit->Catat('pesanan-penjualan.terima', $pesanan, nilaiBaru: [
            'Nomor' => $pesanan->Nomor,
            'TanggalAmbil' => $pesanan->TanggalAmbil->toDateString(),
            'TotalPesanan' => $pesanan->TotalPesanan,
            'UangMuka' => $pesanan->UangMuka,
        ], idPengguna: $kasir->id);

        return StatusItemSinkron::Diterima;
    }

    private static function HitungUangMuka(DataPesananPenjualanPos $data): Uang
    {
        return array_reduce($data->pembayaran, fn (Uang $t, $p): Uang => $t->Tambah($p->jumlah), Uang::Nol());
    }
}
