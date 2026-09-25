<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Data\DataInfoShift;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Data\DataOutletPenjualan;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Data\DataBarisReturPenjualanPos;
use App\Domain\Penjualan\Data\DataNilaiReturBaris;
use App\Domain\Penjualan\Data\DataReturPenjualanPos;
use App\Domain\Penjualan\Data\DataSudahDiretur;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;
use App\Domain\Penjualan\Enum\MetodeRefund;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Enum\StatusReturPenjualan;
use App\Domain\Penjualan\Layanan\PemeriksaPelakuPascaPenjualan;
use App\Domain\Penjualan\Layanan\PenghitungNilaiRetur;
use App\Domain\Penjualan\Layanan\PenyusunJurnalReturPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPajak;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use App\Domain\Penjualan\Model\ReturPenjualanPembayaran;
use App\Domain\Penjualan\Peristiwa\ReturPenjualanDiterima;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Kueri\MutasiDokumen;
use App\Domain\Persediaan\Layanan\PemeriksaStokMinus;
use App\Domain\Persediaan\Layanan\PetaAkunPersediaan;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * F-09 fase 1 (PRD "Rincian F-09 fase 1"): retur penjualan dari item outbox `ReturPenjualan.Buat`. Satu transaksi DB
 * per item; stok & jurnal di transaksi yang sama (aturan #9–#10, J-09.2).
 *
 * Urutan pemeriksaan: (1) Uuid sama → `Duplikat` bila Nomor & TotalRefund sama, selain itu `UuidSudahDipakai`;
 * (2) shift refund milik perangkat ini (`ShiftTidakDitemukan`); (3) penjualan asal di outlet perangkat
 * (`PenjualanTidakDitemukan`), bukan void dan paling lama `BatasHariRetur` hari sejak tanggal bisnisnya
 * (`ReturTidakDiizinkan`); (4) kasir & penyetuju ber-izin `penjualan.void` (`KasirTidakDitemukan`/`TanpaIzin`/
 * `PenyetujuTidakBerwenang`); (5) nomor `RJ/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}` unik per tenant
 * (`NomorTidakValid`/`NomorSudahDipakai`) dan periode terbuka (`PeriodeTerkunci`); (6) baris milik penjualan asal
 * (`BarisTidakDikenal`) dan jumlah ≤ sisa (`JumlahReturMelebihi`); (7) Σ nilai retur = `Ringkasan.TotalRefund`
 * (`HitunganTidakCocok`); (8) refund metode Tunai/Transfer (`MetodeBayarTidakDikenal`/`MetodeBayarBelumDidukung`) dan
 * Σ refund = total (`RefundTidakSesuai`).
 *
 * Stok: setiap mutasi keluar penjualan baris itu dikembalikan proporsional (kumulatif, sisa terakhir tepat habis)
 * sebagai mutasi `ReturPenjualan` bernilai HPP snapshot: `LayakJual` ke lokasi asal (Toko), `Rusak` ke lokasi Rusak
 * outlet (bila tidak ada: ke lokasi asal + `PerluTinjauan`). Status penjualan asal menjadi `Diretur` bila semua baris
 * habis, selain itu `DireturSebagian`.
 */
final class TerimaReturPenjualanPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoShift $infoShift,
        private readonly OutletPenjualan $outletPenjualan,
        private readonly PemeriksaPelakuPascaPenjualan $pelaku,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly PengaturanKasirTenant $pengaturanKasir,
        private readonly PenghitungNilaiRetur $penghitung,
        private readonly MutasiDokumen $mutasiDokumen,
        private readonly InfoProdukStok $infoProduk,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PetaAkunPersediaan $petaAkunPersediaan,
        private readonly PenyusunJurnalReturPenjualan $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataReturPenjualanPos $data): StatusItemSinkron
    {
        if ($data->dibuatPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu retur ada di masa depan. Periksa jam perangkat.', 'DibuatPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) === 1062) {
                if (str_contains($galat->getMessage(), 'UniqReturPenjualanIdTenantNomor')) {
                    throw self::GalatNomorDipakai($data->nomor);
                }

                throw self::GalatUuidDipakai();
            }

            throw $galat;
        }
    }

    private function Proses(DataReturPenjualanPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();

        // (1) Idempotensi per Uuid.
        $lama = ReturPenjualan::query()->where('Uuid', $data->uuid)->lockForUpdate()->first();

        if ($lama !== null) {
            if ($lama->Nomor === $data->nomor && Uang::Dari($lama->TotalRefund)->SamaDengan($data->totalRefund)) {
                return StatusItemSinkron::Duplikat;
            }

            throw self::GalatUuidDipakai();
        }

        // (2) Shift refund.
        $shift = $this->infoShift->CariDiPerangkat($data->uuidShift, $data->idPerangkat);

        if ($shift === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Shift retur ini tidak ditemukan di perangkat ini. Kirim data shift lebih dulu.', 'UuidShift');
        }

        if ($data->dibuatPada->lessThan($shift->dibukaPada->subSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu retur lebih awal dari waktu buka shift.', 'DibuatPada');
        }

        $outlet = $this->outletPenjualan->Ambil($shift->idOutlet, $data->idPerangkat);

        if ($outlet === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Outlet shift retur ini tidak ditemukan.', 'UuidShift');
        }

        // (3) Penjualan asal (kunci baris dokumen, L2).
        $penjualan = Penjualan::query()->where('Uuid', $data->uuidPenjualanAsal)->lockForUpdate()->first();

        if ($penjualan === null || $penjualan->IdOutlet !== $outlet->idOutlet) {
            throw new PelanggaranAturanBisnis('PenjualanTidakDitemukan', 'Penjualan asal retur ini tidak ditemukan di outlet ini.', 'UuidPenjualanAsal', 404);
        }

        $tanggalBisnis = $this->tanggalBisnis->Hitung($outlet->idOutlet, $data->dibuatPada);
        $this->PastikanBolehRetur($data, $penjualan, $tanggalBisnis);

        // (4) Pelaku.
        $kasir = $this->pelaku->CariKasir($idTenant, $data->uuidPengguna, $outlet->idOutlet);
        $penyetuju = $this->pelaku->CariPenyetuju($idTenant, $data->uuidPenyetuju, $outlet->idOutlet);

        // (5) Nomor & periode.
        $this->PeriksaNomor($data, $outlet, $tanggalBisnis);
        $this->penjagaPeriode->PastikanTerbuka($tanggalBisnis);

        // (6) Baris & nilai, (7) total, (8) refund.
        [$detail, $sudah, $nilai] = $this->HitungBaris($data, $penjualan);
        $total = array_reduce($nilai, fn (Uang $t, DataNilaiReturBaris $n): Uang => $t->Tambah($n->nilai), Uang::Nol());

        if (! $total->SamaDengan($data->totalRefund)) {
            throw new PelanggaranAturanBisnis(
                'HitunganTidakCocok',
                "Hitungan Ringkasan.TotalRefund di perangkat ({$data->totalRefund->FormatRupiah()}) berbeda dengan hitungan server ({$total->FormatRupiah()}). Cari ulang struk lalu ulangi retur.",
                'Ringkasan.TotalRefund',
                422,
                ['Perangkat' => $data->totalRefund->KeString(), 'Server' => $total->KeString()],
            );
        }

        $metode = $this->AmbilMetode($data, $total);

        // Simpan dokumen, stok, jurnal.
        $tinjauan = $shift->aktif ? [] : ['ShiftSudahDitutup: retur diterima setelah shift ditutup, belum masuk hitungan kas tutup shift'];
        $idGudangRusak = $this->outletPenjualan->AmbilIdGudangRusak($outlet->idOutlet);

        if ($idGudangRusak === null && array_filter($data->baris, fn (DataBarisReturPenjualanPos $b): bool => $b->kondisi === KondisiBarangRetur::Rusak) !== []) {
            $tinjauan[] = 'LokasiRusakTidakAda: barang rusak dikembalikan ke lokasi Toko karena outlet belum punya lokasi stok Rusak';
        }

        $rencanaStok = $this->RencanakanStok($data, $detail, $sudah, $idGudangRusak);
        $retur = $this->SimpanRetur($data, $penjualan, $shift, $outlet, $kasir->id, $penyetuju->id, $tanggalBisnis, $nilai, $metode, $rencanaStok, $tinjauan);
        $idDetailRetur = $this->SimpanDetail($data, $retur, $detail, $nilai, $rencanaStok);
        $this->SimpanRefund($data, $retur, $metode);
        $persediaan = $this->CatatStok($retur, $rencanaStok, $idDetailRetur, $kasir->id, $data->idPerangkat);

        $biayaLayanan = array_reduce($nilai, fn (Uang $t, DataNilaiReturBaris $n): Uang => $t->Tambah($n->biayaLayanan), Uang::Nol());
        $refundJurnal = [];

        foreach ($data->refund as $r) {
            $refundJurnal[] = [$metode[$r->uuidMetodePembayaran], $r->jumlah];
        }

        $jurnal = $this->penyusunJurnal->Susun($retur, $total, $biayaLayanan, $this->BagiPajak($penjualan, $detail, $nilai), $refundJurnal, $persediaan);

        if (array_filter($jurnal->baris, fn ($b): bool => ! $b->debit->BernilaiNol() || ! $b->kredit->BernilaiNol()) !== []) {
            $retur->IdJurnal = $this->postingJurnal->Jalankan($jurnal)->idJurnal;
            $retur->save();
        }

        $this->UbahStatusPenjualan($penjualan, $detail, $sudah, $nilai, $kasir->id, $retur->Nomor);

        $this->riwayat->Catat(ReturPenjualan::JENIS_DOKUMEN, $retur->Id, null, StatusReturPenjualan::Selesai->value, $kasir->id);
        $this->audit->Catat('penjualan.retur', $retur, nilaiBaru: [
            'Nomor' => $retur->Nomor,
            'NomorPenjualan' => $penjualan->Nomor,
            'TotalRefund' => $retur->TotalRefund,
            'MetodeRefund' => $retur->MetodeRefund->value,
            'Alasan' => $retur->Alasan,
            'DisetujuiOleh' => $penyetuju->nama,
            'PerluTinjauan' => $retur->PerluTinjauan,
        ], idPengguna: $kasir->id);

        // F-14a: retur mengurangi pada tanggal returnya; ringkasan dihitung ulang di antrean setelah commit.
        ReturPenjualanDiterima::dispatch($retur->IdTenant, $retur->IdOutlet, $retur->TanggalBisnis->toDateString(), $retur->Id);

        return StatusItemSinkron::Diterima;
    }

    /**
     * Penjualan asal masih bisa diretur: bukan void, belum diretur penuh, dan paling lama `BatasHariRetur` hari sejak
     * tanggal bisnisnya.
     */
    private function PastikanBolehRetur(DataReturPenjualanPos $data, Penjualan $penjualan, CarbonImmutable $tanggalBisnis): void
    {
        if (! $penjualan->Status->CekBisaDiretur()) {
            $pesan = $penjualan->Status === StatusPenjualan::Void
                ? "Penjualan {$penjualan->Nomor} sudah di-void sehingga tidak bisa diretur."
                : "Semua barang penjualan {$penjualan->Nomor} sudah diretur.";

            throw new PelanggaranAturanBisnis('ReturTidakDiizinkan', $pesan, 'UuidPenjualanAsal', 422, ['Status' => $penjualan->Status->value]);
        }

        if ($data->dibuatPada->lessThan(CarbonImmutable::parse($penjualan->DibuatOfflinePada)->subSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu retur lebih awal dari waktu penjualan.', 'DibuatPada');
        }

        $batas = $this->pengaturanKasir->Ambil()->batasHariRetur;
        $tanggalJual = CarbonImmutable::parse($penjualan->TanggalBisnis->toDateString());
        $batasSampai = $tanggalJual->addDays($batas);

        if ($tanggalBisnis->greaterThan($batasSampai)) {
            throw new PelanggaranAturanBisnis(
                'ReturTidakDiizinkan',
                "Batas retur {$batas} hari untuk penjualan {$penjualan->Nomor} sudah lewat (sampai {$batasSampai->format('d/m/Y')}).",
                'UuidPenjualanAsal',
                422,
                ['BatasHariRetur' => $batas, 'BatasSampai' => $batasSampai->toDateString()],
            );
        }
    }

    /** `RJ/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}`; YYMMDD = tanggal bisnis atau tanggal lokal outlet. */
    private function PeriksaNomor(DataReturPenjualanPos $data, DataOutletPenjualan $outlet, CarbonImmutable $tanggalBisnis): void
    {
        $pola = '#^RJ/'.preg_quote($outlet->kodeOutlet, '#').'/(\d{6})/'.preg_quote($outlet->kodePerangkat, '#').'-\d{4,}$#';
        $tanggalBoleh = [$tanggalBisnis->format('ymd'), $data->dibuatPada->setTimezone($outlet->zonaWaktu)->format('ymd')];

        if (preg_match($pola, $data->nomor, $cocok) !== 1 || ! in_array($cocok[1], $tanggalBoleh, true)) {
            throw new PelanggaranAturanBisnis(
                'NomorTidakValid',
                "Nomor {$data->nomor} tidak sesuai format RJ/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001.",
                'Nomor',
                detail: ['Contoh' => "RJ/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001"],
            );
        }

        if (ReturPenjualan::query()->where('Nomor', $data->nomor)->exists()) {
            throw self::GalatNomorDipakai($data->nomor);
        }
    }

    /**
     * Baris penjualan asal per baris retur, akumulasi retur sebelumnya, dan nilai retur baris ini.
     *
     * @return array{0: list<PenjualanDetail>, 1: array<int, DataSudahDiretur>, 2: list<DataNilaiReturBaris>}
     */
    private function HitungBaris(DataReturPenjualanPos $data, Penjualan $penjualan): array
    {
        $semua = PenjualanDetail::query()->where('IdPenjualan', $penjualan->Id)->get()->keyBy('Uuid');
        $sudah = $this->penghitung->AmbilSudahDiretur(array_values(array_map('intval', $semua->pluck('Id')->all())));
        $detail = [];
        $nilai = [];

        foreach ($data->baris as $indeks => $baris) {
            $d = $semua->get($baris->uuidPenjualanDetail);

            if (! $d instanceof PenjualanDetail) {
                throw new PelanggaranAturanBisnis('BarisTidakDikenal', 'Baris ke-'.($indeks + 1).' bukan bagian dari penjualan asal.', "Baris.{$indeks}.UuidPenjualanDetail");
            }

            $s = $sudah[$d->Id] ?? DataSudahDiretur::Kosong();
            $sisa = $this->penghitung->HitungSisa($d, $s);

            if ($baris->jumlah->Bandingkan($sisa) > 0) {
                throw new PelanggaranAturanBisnis(
                    'JumlahReturMelebihi',
                    "Jumlah retur {$d->NamaProduk} melebihi sisa yang bisa diretur (".PemeriksaStokMinus::FormatJumlah($sisa).').',
                    "Baris.{$indeks}.Jumlah",
                    422,
                    ['UuidPenjualanDetail' => $d->Uuid, 'JumlahBisaDiretur' => $sisa->KeString()],
                );
            }

            $detail[] = $d;
            $nilai[] = $this->penghitung->Hitung($d, $s, $baris->jumlah);
        }

        return [$detail, $sudah, $nilai];
    }

    /**
     * Refund fase 1: metode jenis Tunai atau Transfer, Σ refund = total nilai retur.
     *
     * @return array<string, MetodePembayaran> kunci = Uuid metode
     */
    private function AmbilMetode(DataReturPenjualanPos $data, Uang $total): array
    {
        $uuid = array_values(array_unique(array_map(fn ($r): string => $r->uuidMetodePembayaran, $data->refund)));
        $metode = $uuid === [] ? [] : MetodePembayaran::query()->whereIn('Uuid', $uuid)->get()->keyBy('Uuid')->all();
        $jumlah = Uang::Nol();

        foreach ($data->refund as $indeks => $r) {
            $m = $metode[$r->uuidMetodePembayaran] ?? null;

            if (! $m instanceof MetodePembayaran) {
                throw new PelanggaranAturanBisnis('MetodeBayarTidakDikenal', 'Metode refund tidak ditemukan.', "Refund.{$indeks}.UuidMetodePembayaran");
            }

            if (! in_array($m->Jenis, [JenisMetodePembayaran::Tunai, JenisMetodePembayaran::Transfer], true)) {
                throw new PelanggaranAturanBisnis('MetodeBayarBelumDidukung', "Refund lewat {$m->Jenis->AmbilLabel()} belum didukung. Pakai tunai atau transfer manual.", "Refund.{$indeks}.UuidMetodePembayaran");
            }

            $jumlah = $jumlah->Tambah($r->jumlah);
        }

        if (! $jumlah->SamaDengan($total)) {
            throw new PelanggaranAturanBisnis(
                'RefundTidakSesuai',
                "Jumlah refund {$jumlah->FormatRupiah()} harus sama dengan nilai retur {$total->FormatRupiah()}.",
                'Refund',
                422,
                ['TotalRetur' => $total->KeString(), 'TotalRefund' => $jumlah->KeString()],
            );
        }

        return $metode;
    }

    /**
     * Rencana mutasi masuk per mutasi keluar penjualan baris itu. Bagian kumulatif: sebelum = asal × sudah ÷ jual,
     * sesudah = asal × (sudah + retur) ÷ jual (retur terakhir = asal penuh); mutasi = sesudah − sebelum, sehingga
     * Σ retur sebuah baris tepat sama dengan mutasi asalnya.
     *
     * @param  list<PenjualanDetail>  $detail
     * @param  array<int, DataSudahDiretur>  $sudah
     * @return list<array{Indeks: int, KunciBaris: string, IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>
     */
    private function RencanakanStok(DataReturPenjualanPos $data, array $detail, array $sudah, ?int $idGudangRusak): array
    {
        if ($detail === []) {
            return [];
        }

        $mutasi = [];

        foreach ($this->mutasiDokumen->AmbilRingkasan(JenisReferensiMutasi::Penjualan, $detail[0]->IdPenjualan) as $m) {
            $mutasi[$m['IdReferensiDetail'] ?? 0][] = $m;
        }

        $rencana = [];

        foreach ($data->baris as $indeks => $baris) {
            $d = $detail[$indeks];
            $jual = BigDecimal::of($d->Jumlah);
            $sebelum = ($sudah[$d->Id] ?? DataSudahDiretur::Kosong())->jumlah->KeDesimal();
            $sesudah = $sebelum->plus($baris->jumlah->KeDesimal());
            $terakhir = $sesudah->isEqualTo($jual);

            foreach ($mutasi[$d->Id] ?? [] as $m) {
                $asalJumlah = BigDecimal::of($m['Jumlah'])->abs();
                $asalNilai = BigDecimal::of($m['TotalHpp'])->abs();
                $jumlah = ($terakhir ? $asalJumlah : self::Bagian($asalJumlah, $sesudah, $jual, Kuantitas::SKALA))
                    ->minus(self::Bagian($asalJumlah, $sebelum, $jual, Kuantitas::SKALA));

                if ($jumlah->isZero()) {
                    continue;
                }

                $nilai = ($terakhir ? $asalNilai->toScale(Uang::SKALA) : self::Bagian($asalNilai, $sesudah, $jual, Uang::SKALA))
                    ->minus(self::Bagian($asalNilai, $sebelum, $jual, Uang::SKALA));
                $rusak = $baris->kondisi === KondisiBarangRetur::Rusak && $idGudangRusak !== null;

                $rencana[] = [
                    'Indeks' => $indeks,
                    'KunciBaris' => $baris->uuid.'/'.$m['Id'],
                    'IdProduk' => $m['IdProduk'],
                    'IdGudang' => $rusak ? (int) $idGudangRusak : $m['IdGudang'],
                    'Jumlah' => Kuantitas::Dari($jumlah),
                    'Nilai' => Uang::Dari($nilai),
                    'HppSatuan' => BigDecimal::of($m['HppSatuan'])->abs(),
                ];
            }
        }

        return $rencana;
    }

    /**
     * @param  list<DataNilaiReturBaris>  $nilai
     * @param  array<string, MetodePembayaran>  $metode
     * @param  list<array{Indeks: int, KunciBaris: string, IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>  $rencana
     * @param  list<string>  $tinjauan
     */
    private function SimpanRetur(
        DataReturPenjualanPos $data,
        Penjualan $penjualan,
        DataInfoShift $shift,
        DataOutletPenjualan $outlet,
        int $idKasir,
        int $idPenyetuju,
        CarbonImmutable $tanggalBisnis,
        array $nilai,
        array $metode,
        array $rencana,
        array $tinjauan,
    ): ReturPenjualan {
        $jumlah = fn (callable $ambil): string => array_reduce($nilai, fn (Uang $t, DataNilaiReturBaris $n): Uang => $t->Tambah($ambil($n)), Uang::Nol())->KeString();
        $tunai = Uang::Nol();
        $jenis = [];

        foreach ($data->refund as $r) {
            $jenisMetode = $metode[$r->uuidMetodePembayaran]->Jenis;
            $jenis[$jenisMetode->value] = true;

            if ($jenisMetode === JenisMetodePembayaran::Tunai) {
                $tunai = $tunai->Tambah($r->jumlah);
            }
        }

        $metodeRefund = match (true) {
            count($jenis) > 1 => MetodeRefund::Campuran,
            isset($jenis[JenisMetodePembayaran::Transfer->value]) => MetodeRefund::Transfer,
            default => MetodeRefund::Tunai,
        };

        return ReturPenjualan::query()->create([
            'Uuid' => $data->uuid,
            'IdPenjualanAsal' => $penjualan->Id,
            'IdOutlet' => $outlet->idOutlet,
            'IdShift' => $shift->id,
            'IdPerangkat' => $data->idPerangkat,
            'Nomor' => $data->nomor,
            'Status' => StatusReturPenjualan::Selesai,
            'Alasan' => mb_substr($data->alasan, 0, 255),
            'MetodeRefund' => $metodeRefund,
            'IdPengguna' => $idKasir,
            'IdPenyetuju' => $idPenyetuju,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'TotalNilai' => $jumlah(fn (DataNilaiReturBaris $n): Uang => $n->nilai),
            'TotalPajak' => $jumlah(fn (DataNilaiReturBaris $n): Uang => $n->pajak),
            'TotalBiayaLayanan' => $jumlah(fn (DataNilaiReturBaris $n): Uang => $n->biayaLayanan),
            'TotalRefund' => $jumlah(fn (DataNilaiReturBaris $n): Uang => $n->nilai),
            'RefundTunai' => $tunai->KeString(),
            'TotalHpp' => array_reduce($rencana, fn (Uang $t, array $r): Uang => $t->Tambah($r['Nilai']), Uang::Nol())->KeString(),
            'PerluTinjauan' => $tinjauan !== [],
            'AlasanTinjauan' => $tinjauan === [] ? null : mb_substr(implode('; ', $tinjauan), 0, 255),
            'DibuatOfflinePada' => $data->dibuatPada,
            'DiterimaPada' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  list<PenjualanDetail>  $detail
     * @param  list<DataNilaiReturBaris>  $nilai
     * @param  list<array{Indeks: int, KunciBaris: string, IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>  $rencana
     * @return list<int> Id baris retur, urutan sama dengan baris masukan
     */
    private function SimpanDetail(DataReturPenjualanPos $data, ReturPenjualan $retur, array $detail, array $nilai, array $rencana): array
    {
        $id = [];

        foreach ($data->baris as $indeks => $baris) {
            $d = $detail[$indeks];
            $n = $nilai[$indeks];
            $stok = array_values(array_filter($rencana, fn (array $r): bool => $r['Indeks'] === $indeks));

            $id[] = ReturPenjualanDetail::query()->create([
                'Uuid' => $baris->uuid,
                'IdReturPenjualan' => $retur->Id,
                'IdPenjualanDetail' => $d->Id,
                'Urutan' => $indeks + 1,
                'IdProduk' => $d->IdProduk,
                'NamaProduk' => $d->NamaProduk,
                'Jumlah' => $n->jumlah->KeString(),
                'JumlahDasar' => $n->jumlahDasar->KeString(),
                'NilaiBaris' => $n->nilai->KeString(),
                'Pajak' => $n->pajak->KeString(),
                'BiayaLayanan' => $n->biayaLayanan->KeString(),
                'HppSatuan' => $d->HppSatuan,
                'TotalHpp' => array_reduce($stok, fn (Uang $t, array $r): Uang => $t->Tambah($r['Nilai']), Uang::Nol())->KeString(),
                'Kondisi' => $baris->kondisi,
                'IdGudang' => $stok[0]['IdGudang'] ?? null,
            ])->Id;
        }

        return $id;
    }

    /**
     * @param  array<string, MetodePembayaran>  $metode
     */
    private function SimpanRefund(DataReturPenjualanPos $data, ReturPenjualan $retur, array $metode): void
    {
        foreach ($data->refund as $indeks => $r) {
            $m = $metode[$r->uuidMetodePembayaran];

            ReturPenjualanPembayaran::query()->create([
                'Uuid' => $r->uuid,
                'IdReturPenjualan' => $retur->Id,
                'Urutan' => $indeks + 1,
                'IdMetodePembayaran' => $m->Id,
                'JenisMetode' => $m->Jenis,
                'NamaMetode' => mb_substr($m->Nama, 0, 100),
                'Jumlah' => $r->jumlah->KeString(),
            ]);
        }
    }

    /**
     * Mutasi `ReturPenjualan` (masuk, nilai ditentukan HPP snapshot) lalu perubahan nilai per peran akun persediaan.
     *
     * @param  list<array{Indeks: int, KunciBaris: string, IdProduk: int, IdGudang: int, Jumlah: Kuantitas, Nilai: Uang, HppSatuan: BigDecimal}>  $rencana
     * @param  list<int>  $idDetailRetur
     * @return array<string, Uang> nilai PeranAkun persediaan → nilai stok yang kembali
     */
    private function CatatStok(ReturPenjualan $retur, array $rencana, array $idDetailRetur, int $idKasir, int $idPerangkat): array
    {
        if ($rencana === []) {
            return [];
        }

        $hasil = $this->catatMutasi->Jalankan(new DataDokumenMutasi(
            jenisReferensi: JenisReferensiMutasi::ReturPenjualan,
            idReferensi: $retur->Id,
            uuidReferensi: $retur->Uuid,
            nomorReferensi: $retur->Nomor,
            tanggalBisnis: CarbonImmutable::parse($retur->TanggalBisnis->toDateString()),
            idPengguna: $idKasir,
            idPerangkat: $idPerangkat,
            baris: array_map(fn (array $r): DataBarisMutasi => new DataBarisMutasi(
                kunciBaris: $r['KunciBaris'],
                idProduk: $r['IdProduk'],
                idGudang: $r['IdGudang'],
                jenisMutasi: JenisMutasi::ReturPenjualan,
                jumlah: $r['Jumlah'],
                modeNilai: ModeNilaiMutasi::Ditentukan,
                nilai: $r['Nilai'],
                hppSatuan: $r['HppSatuan'],
                idReferensiDetail: $idDetailRetur[$r['Indeks']],
            ), $rencana),
        ));

        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map(fn (array $r): int => $r['IdProduk'], $rencana))), true);
        $persediaan = [];

        foreach ($hasil->baris as $b) {
            $peran = $this->petaAkunPersediaan->UntukJenis($produk[$b->idProduk]->jenis)->value;
            $persediaan[$peran] = ($persediaan[$peran] ?? Uang::Nol())->Tambah($b->totalHpp);
        }

        return $persediaan;
    }

    /**
     * Pajak bagian retur per kode jenis pajak. Baris dengan satu kode pajak: seluruhnya ke kode itu; beberapa kode:
     * dibagi menurut bobot tarif × pengali DPP (sisa ke kode terakhir). Baris tanpa snapshot memakai kode pajak dokumen.
     *
     * @param  list<PenjualanDetail>  $detail
     * @param  list<DataNilaiReturBaris>  $nilai
     * @return array<string, Uang>
     */
    private function BagiPajak(Penjualan $penjualan, array $detail, array $nilai): array
    {
        $kodeDokumen = null;
        $hasil = [];

        foreach ($detail as $indeks => $d) {
            $pajak = $nilai[$indeks]->pajak;

            if ($pajak->BernilaiNol()) {
                continue;
            }

            $snapshot = $d->SnapshotPajak ?? [];

            if ($snapshot === []) {
                $kodeDokumen ??= (string) (PenjualanPajak::query()->where('IdPenjualan', $penjualan->Id)->orderBy('Id')->value('KodeJenisPajak') ?? '');
                $hasil[$kodeDokumen] = ($hasil[$kodeDokumen] ?? Uang::Nol())->Tambah($pajak);

                continue;
            }

            $bobot = array_map(fn (array $p): BigDecimal => BigDecimal::of($p['Tarif'])->multipliedBy($p['PengaliDppPembilang'])->dividedBy($p['PengaliDppPenyebut'], 10, RoundingMode::HalfUp), $snapshot);
            $totalBobot = array_reduce($bobot, fn (BigDecimal $t, BigDecimal $b): BigDecimal => $t->plus($b), BigDecimal::zero());
            $sisa = $pajak;

            foreach ($snapshot as $k => $p) {
                $bagian = $k === array_key_last($snapshot) || $totalBobot->isZero()
                    ? $sisa
                    : $pajak->Kali($bobot[$k]->dividedBy($totalBobot, 10, RoundingMode::HalfUp));
                $sisa = $sisa->Kurangi($bagian);
                $hasil[$p['Kode']] = ($hasil[$p['Kode']] ?? Uang::Nol())->Tambah($bagian);

                if ($sisa->BernilaiNol()) {
                    break;
                }
            }
        }

        return $hasil;
    }

    /**
     * `Diretur` bila semua baris penjualan habis diretur, selain itu `DireturSebagian`; dicatat di riwayat status.
     *
     * @param  list<PenjualanDetail>  $detail
     * @param  array<int, DataSudahDiretur>  $sudah
     * @param  list<DataNilaiReturBaris>  $nilai
     */
    private function UbahStatusPenjualan(Penjualan $penjualan, array $detail, array $sudah, array $nilai, int $idKasir, string $nomorRetur): void
    {
        $diretur = [];

        foreach ($sudah as $idDetail => $s) {
            $diretur[$idDetail] = $s->jumlah;
        }

        foreach ($detail as $indeks => $d) {
            $diretur[$d->Id] = ($diretur[$d->Id] ?? Kuantitas::Nol())->Tambah($nilai[$indeks]->jumlah);
        }

        $habis = true;

        foreach (PenjualanDetail::query()->where('IdPenjualan', $penjualan->Id)->get(['Id', 'Jumlah']) as $d) {
            if (($diretur[$d->Id] ?? Kuantitas::Nol())->Bandingkan(Kuantitas::Dari($d->Jumlah)) < 0) {
                $habis = false;

                break;
            }
        }

        $lama = $penjualan->Status;
        $baru = $habis ? StatusPenjualan::Diretur : StatusPenjualan::DireturSebagian;

        if ($lama === $baru) {
            return;
        }

        $penjualan->Status = $baru;
        $penjualan->save();
        $this->riwayat->Catat(Penjualan::JENIS_DOKUMEN, $penjualan->Id, $lama->value, $baru->value, $idKasir, "Retur {$nomorRetur}");
    }

    /**
     * nilai × bagian ÷ total, dibulatkan HalfUp ke skala tertentu.
     *
     * @param  int<0, max>  $skala
     */
    private static function Bagian(BigDecimal $nilai, BigDecimal $bagian, BigDecimal $total, int $skala): BigDecimal
    {
        return $nilai->multipliedBy($bagian)->dividedBy($total, $skala, RoundingMode::HalfUp);
    }

    private static function GalatNomorDipakai(string $nomor): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$nomor} sudah dipakai retur lain.", 'Nomor', 409);
    }

    private static function GalatUuidDipakai(): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik retur ini sudah dipakai data lain. Buat ulang retur di aplikasi.', 'Uuid');
    }
}
