<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Data\DataBarisKomisi;
use App\Domain\Karyawan\Layanan\PencatatKomisiPenjualan;
use App\Domain\Kasir\Kueri\InfoShift;
use App\Domain\Katalog\Data\DataKebutuhanStok;
use App\Domain\Katalog\Data\DataProdukPenjualan;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\KomposisiPenjualan;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Data\DataOutletPenjualan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Kueri\KreditPelanggan;
use App\Domain\Pelanggan\Layanan\PencatatPiutangPenjualan;
use App\Domain\Pelanggan\Layanan\PencatatPoinPenjualan;
use App\Domain\Pemenuhan\Aksi\KirimKeDapur;
use App\Domain\Pemenuhan\Data\DataBarisKirimDapur;
use App\Domain\Pemenuhan\Data\DataKirimDapur;
use App\Domain\Penjualan\Data\DataBarisPenjualanPos;
use App\Domain\Penjualan\Data\DataPajakPenjualanPos;
use App\Domain\Penjualan\Data\DataPenjualanPos;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Kalkulasi\DataBarisKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\DataPembayaranKalkulasi;
use App\Domain\Penjualan\Kalkulasi\HasilKalkulasi;
use App\Domain\Penjualan\Kalkulasi\HasilPajakKalkulasi;
use App\Domain\Penjualan\Kalkulasi\MesinKalkulasi;
use App\Domain\Penjualan\Kalkulasi\MesinPromo;
use App\Domain\Penjualan\Kalkulasi\PromoTerpakai;
use App\Domain\Penjualan\Layanan\PemeriksaDiskonPenjualan;
use App\Domain\Penjualan\Layanan\PemeriksaPromoPenjualan;
use App\Domain\Penjualan\Layanan\PemeriksaSnapshotPengaturanPenjualan;
use App\Domain\Penjualan\Layanan\PenautTagihanQrisPenjualan;
use App\Domain\Penjualan\Layanan\PenutupPesananPenjualan;
use App\Domain\Penjualan\Layanan\PenutupPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenyusunJurnalPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPajak;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Peristiwa\PenjualanDiterima;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Layanan\PemeriksaStokMinus;
use App\Domain\Persediaan\Layanan\PetaAkunPersediaan;
use App\Domain\Promo\Layanan\PemakaiVoucher;
use App\Domain\Promo\Layanan\PencatatKlaimPromoPemasok;
use App\Domain\Promo\Layanan\PencatatPemakaianPromo;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * F-07b (PRD "Rincian F-07b"): penjualan lunas yang dibuat di perangkat (bisa offline) diterima server lewat sinkron.
 * Satu transaksi DB per item; stok & jurnal di transaksi yang sama (aturan #9–#10).
 *
 * Urutan pemeriksaan: (1) Uuid sama → `Duplikat` bila Nomor & TotalAkhir sama, selain itu `UuidSudahDipakai` (juga
 * saat dua kiriman bersamaan bertabrakan di indeks unik: dibaca ulang lalu dibandingkan); (2) shift milik perangkat ini
 * (`ShiftTidakDitemukan`); (3) kasir anggota tenant (`KasirTidakDitemukan`); (4) nomor BR-07.1
 * `INV/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}`, unik per tenant (`NomorTidakValid`/`NomorSudahDipakai`); (5) produk (`ProdukTidakDikenal`/`ProdukTidakBisaDijual`/`PelacakanBelumDidukung`/
 * `SatuanTidakDikenal`); (6) tarif pajak snapshot = `TarifPajak` terbit yang berlaku (`TarifPajakTidakSah`); (9) metode
 * bayar (`MetodeBayarTidakDikenal`/`MetodeBayarBelumDidukung`); (8) hitung ulang `MesinKalkulasi` = `Ringkasan`
 * (`HitunganTidakCocok`); (7) BR-07.3 diskon (`PenyetujuTidakBerwenang`); (9) pembayaran (`PembayaranTidakValid`/
 * `PembayaranKurang`).
 *
 * Keadaan yang bisa berubah setelah transaksi offline tidak menolak (§18.3, PRD v1.46 "Tindak lanjut tinjauan"):
 * penjualan diterima dan ditandai `PerluTinjauan` dengan alasan `StokTidakCukup` (mutasi tetap dicatat),
 * `PilihanTidakDikenal` (bahan pilihan yang dihapus tidak dikurangi), `ProdukDihapus` (produk dihapus, hanya mungkin
 * bila belum pernah dipakai, BR-03.2), `IzinBerubah` (kasir/penyetuju masih anggota tenant tetapi tidak lagi di outlet
 * atau tanpa izin berjualan), `DiskonMelebihiBatas` (BR-07.3 dilanggar menurut batas yang berlaku saat diterima),
 * `PengaturanBerbeda`/`PajakBerbeda` (snapshot pengaturan & pajak berbeda dari pengaturan server),
 * `ShiftSudahDitutup`, `PeriodeTerkunci` (F-15/§18: tanggal bisnis di periode terkunci, jurnal & stok dibukukan
 * di hari pertama periode terbuka berikutnya), dan F-08 `QrisDinamis*` (tagihan QRIS dinamis bermasalah, lihat
 * `PenautTagihanQrisPenjualan`).
 */
final class TerimaPenjualanPos
{
    private const TOLERANSI_JAM_DETIK = 600;

    /** Panjang kolom `Penjualan.AlasanTinjauan`. */
    private const PANJANG_ALASAN_TINJAUAN = 1000;

    private const JENIS_TIDAK_BISA_DIJUAL = [JenisProduk::IndukVarian, JenisProduk::BahanBaku, JenisProduk::Konsinyasi];

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoShift $infoShift,
        private readonly OutletPenjualan $outletPenjualan,
        private readonly AnggotaOutlet $anggota,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly KomposisiPenjualan $komposisi,
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly PengaturanKasirTenant $pengaturanKasir,
        private readonly PemeriksaDiskonPenjualan $pemeriksaDiskon,
        private readonly PemeriksaSnapshotPengaturanPenjualan $pemeriksaSnapshot,
        private readonly MesinKalkulasi $mesin,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PetaAkunPersediaan $petaAkunPersediaan,
        private readonly PenyusunJurnalPenjualan $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
        private readonly PenutupPesananTerbuka $penutupPesanan,
        private readonly KirimKeDapur $kirimDapur,
        private readonly IdentitasPelanggan $identitasPelanggan,
        private readonly PencatatPoinPenjualan $poin,
        private readonly PemeriksaPromoPenjualan $pemeriksaPromo,
        private readonly PencatatPemakaianPromo $pemakaianPromo,
        private readonly PencatatKlaimPromoPemasok $klaimPemasok,
        private readonly PemakaiVoucher $voucher,
        private readonly PencatatPiutangPenjualan $piutang,
        private readonly PencatatKomisiPenjualan $komisi,
        private readonly KreditPelanggan $kredit,
        private readonly PenutupPesananPenjualan $penutupPraPesan,
        private readonly PenautTagihanQrisPenjualan $penautQris,
    ) {}

    public function Jalankan(DataPenjualanPos $data): StatusItemSinkron
    {
        if ($data->dibuatPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu penjualan ada di masa depan. Periksa jam perangkat.', 'DibuatPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => Penjualan::JalankanPenerimaan(fn (): StatusItemSinkron => $this->Proses($data)));
        } catch (QueryException $galat) {
            return $this->SelesaikanBentrokUnik($galat, $data->uuid, $data->nomor, $data->ringkasan->totalAkhir);
        }
    }

    /**
     * Pelanggaran indeks unik (1062) karena dua kiriman bersamaan lolos pemeriksaan idempotensi: baca ulang penjualan
     * ber-Uuid sama; bila Nomor & TotalAkhir sama = `Duplikat` (kiriman ulang yang sah), selain itu `NomorSudahDipakai`
     * (bentrok nomor) atau `UuidSudahDipakai`. Galat database lain diteruskan.
     */
    private function SelesaikanBentrokUnik(QueryException $galat, string $uuid, string $nomor, Uang $totalAkhir): StatusItemSinkron
    {
        if (($galat->errorInfo[1] ?? null) !== 1062) {
            throw $galat;
        }

        $lama = Penjualan::query()->where('Uuid', $uuid)->first();

        if ($lama !== null && $lama->Nomor === $nomor && Uang::Dari($lama->TotalAkhir)->SamaDengan($totalAkhir)) {
            return StatusItemSinkron::Duplikat;
        }

        if ($lama === null && str_contains($galat->getMessage(), 'UniqPenjualanIdTenantNomor')) {
            throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$nomor} sudah dipakai penjualan lain.", 'Nomor', 409);
        }

        throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik penjualan ini sudah dipakai data lain. Buat ulang transaksi di aplikasi.', 'Uuid');
    }

    private function Proses(DataPenjualanPos $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();

        // (1) Idempotensi per Uuid.
        $lama = Penjualan::query()->where('Uuid', $data->uuid)->lockForUpdate()->first();

        if ($lama !== null) {
            if ($lama->Nomor === $data->nomor && Uang::Dari($lama->TotalAkhir)->SamaDengan($data->ringkasan->totalAkhir)) {
                return StatusItemSinkron::Duplikat;
            }

            throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik penjualan ini sudah dipakai data lain. Buat ulang transaksi di aplikasi.', 'Uuid');
        }

        // (2) Shift milik perangkat ini (outbox FIFO mengirim shift lebih dulu).
        $shift = $this->infoShift->CariDiPerangkat($data->uuidShift, $data->idPerangkat);

        if ($shift === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Shift penjualan ini tidak ditemukan di perangkat ini. Kirim data shift lebih dulu.', 'UuidShift');
        }

        if ($data->dibuatPada->lessThan($shift->dibukaPada->subSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu penjualan lebih awal dari waktu buka shift.', 'DibuatPada');
        }

        $outlet = $this->outletPenjualan->Ambil($shift->idOutlet, $data->idPerangkat);

        if ($outlet === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDitemukan', 'Outlet shift penjualan ini tidak ditemukan.', 'UuidShift');
        }

        // (3) Kasir: hanya pengguna yang bukan anggota tenant yang ditolak; izin/outlet yang berubah setelah transaksi
        // offline menjadi alasan tinjauan (PRD v1.46).
        $cariKasir = $this->anggota->CariDiTenant($idTenant, $data->uuidPengguna, $outlet->idOutlet);

        if ($cariKasir === null) {
            throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Kasir ini bukan anggota usaha ini.', 'UuidPengguna');
        }

        [$kasir, $kasirDiOutlet] = $cariKasir;
        $tinjauan = [];
        $izinBerubah = array_values(array_filter([
            $kasirDiOutlet ? null : "{$kasir->nama} tidak lagi terdaftar di outlet ini",
            $kasir->CekIzin(IzinTenant::PenjualanBuat->value) ? null : "{$kasir->nama} tidak lagi punya izin berjualan",
        ]));

        // (4) Nomor & periode.
        $tanggalBisnis = $this->tanggalBisnis->Hitung($outlet->idOutlet, $data->dibuatPada);
        $this->PeriksaNomor($data, $outlet, $tanggalBisnis);
        // F-15/§18: periode terkunci tidak menolak penjualan offline; jurnal & stok dibukukan di periode terbuka berikutnya.
        $pergeseranPeriode = $this->penjagaPeriode->JelaskanPergeseran($tanggalBisnis);

        if ($pergeseranPeriode !== null) {
            $tinjauan['PeriodeTerkunci'] = $pergeseranPeriode;
        }

        // (5) Produk & satuan, (6) tarif pajak, (9) metode bayar.
        $produk = $this->AmbilProduk($data);
        $this->PeriksaTarifPajak($data, $outlet, $tanggalBisnis);
        $metode = $this->AmbilMetode($data);

        // (8) Hitung ulang.
        [$hasil, $hasilDasar, $dasarKalkulasi, $promoPerangkat] = $this->HitungUlang($data, $metode);

        // (7) BR-07.3 diskon manual (persen efektif dari hasil mesin, PRD v1.46 (c)).
        $pengaturan = $this->pengaturanKasir->Ambil();
        $pemeriksaanDiskon = $this->pemeriksaDiskon->Periksa(
            $this->KumpulkanDiskon($data, $hasilDasar),
            $kasir,
            $data->uuidPenyetujuDiskon,
            $idTenant,
            $outlet->idOutlet,
            $pengaturan,
        );
        $penyetuju = $pemeriksaanDiskon->penyetuju;

        if (isset($pemeriksaanDiskon->tinjauan['IzinBerubah'])) {
            $izinBerubah[] = substr($pemeriksaanDiskon->tinjauan['IzinBerubah'], strlen('IzinBerubah: '));
        }

        if ($izinBerubah !== []) {
            $tinjauan['IzinBerubah'] = 'IzinBerubah: '.implode('; ', $izinBerubah);
        }

        $tinjauan += array_diff_key($pemeriksaanDiskon->tinjauan, ['IzinBerubah' => true]);

        // PRD v1.46 (a): snapshot pengaturan & pajak dibandingkan dengan pengaturan server pada tanggal bisnis.
        $tinjauan += $this->pemeriksaSnapshot->Periksa($data, $produk, $outlet, $pengaturan, $tanggalBisnis);

        // (9) Pembayaran.
        [$totalDibayar, $bersih] = $this->PeriksaPembayaran($data, $metode, $hasil);

        // F-07 mode meja: pesanan terbuka yang dibayar (dikunci sebelum penjualan dibuat agar IdPesananTerbuka tersimpan).
        [$pesanan, $tinjauanPesanan] = $this->penutupPesanan->Cari($data->uuidPesananTerbuka, $outlet->idOutlet);
        $tinjauan += $tinjauanPesanan;

        // F-12 bagian 2: pre-order yang diambil (dikunci sebelum penjualan dibuat). Masalah = diterima + tinjauan.
        [$praPesan, $masalahUangMuka] = $this->penutupPraPesan->Cari($data->uuidPesananPenjualan, $outlet->idOutlet);

        // F-16a: pelanggan dari POS (Uuid atau alias). Belum dikenal = penjualan tetap diterima tanpa pelanggan.
        $idPelanggan = $data->uuidPelanggan === null ? null : $this->identitasPelanggan->CariId($data->uuidPelanggan);

        if ($data->uuidPelanggan !== null && $idPelanggan === null) {
            $tinjauan['PelangganTidakDikenal'] = 'PelangganTidakDikenal: pelanggan belum diterima server, penjualan disimpan tanpa pelanggan';
        }

        // F-12: penjualan tempo (BR-12.1). Melebihi limit / piutang lewat jatuh tempo butuh penyetuju `penjualan.tempo.setujui`
        // (kasir sendiri ber-izin = tanpa PIN). Data cache perangkat basi = tetap diterima + tinjauan.
        $tempo = $this->AmbilJumlahTempo($data, $metode);
        $penyetujuTempo = null;

        if ($tempo !== null) {
            $masalahTempo = $idPelanggan === null
                ? ['pelanggan belum diterima server']
                : $this->kredit->Periksa($idPelanggan, $tempo, $tanggalBisnis, $pengaturan->batasHariLewatJatuhTempo);

            if ($masalahTempo !== [] && $idPelanggan !== null) {
                $calon = $data->uuidPenyetujuTempo === null ? null : $this->anggota->CariDiTenant($idTenant, $data->uuidPenyetujuTempo, $outlet->idOutlet);
                $penyetujuTempo = match (true) {
                    $calon !== null && $calon[0]->CekIzin(IzinTenant::PenjualanTempoSetujui->value) => $calon[0],
                    $kasir->CekIzin(IzinTenant::PenjualanTempoSetujui->value) => $kasir,
                    default => null,
                };

                if ($penyetujuTempo !== null) {
                    $masalahTempo = [];
                }
            }

            if ($masalahTempo !== []) {
                $tinjauan['TempoBermasalah'] = 'TempoBermasalah: '.implode('; ', $masalahTempo).' (tanpa penyetuju)';
            }
        }

        // F-16c: promo dievaluasi ulang dengan definisi server; beda dengan perangkat = diterima + tinjauan.
        // F-16c bagian 2: promo wajib voucher hanya berlaku dengan voucher yang dikirim perangkat.
        // F-16c bagian 3: metode bayar, ulang tahun, transaksi pertama & batas per pelanggan dinilai dari data server.
        $uuidPromoVoucher = $this->voucher->AmbilUuidPromo($data->kodeVoucher);
        $pemeriksaanPromo = $this->pemeriksaPromo->Periksa($data, $dasarKalkulasi, $produk, $outlet, $idPelanggan, $tanggalBisnis->toDateString(), $promoPerangkat, $uuidPromoVoucher === null ? [] : [$uuidPromoVoucher]);
        $masalahPromo = $pemeriksaanPromo->masalah;

        // Simpan dokumen, stok, jurnal.
        $penjualan = $this->SimpanPenjualan($data, $shift->id, $outlet, $kasir, $penyetuju, $tanggalBisnis, $hasil, $totalDibayar, $pesanan?->Id, $idPelanggan, $penyetujuTempo?->id, $praPesan?->Id);
        $detail = $this->SimpanDetail($data, $penjualan, $produk, $hasil);
        $this->penutupPesanan->Tutup($pesanan, $penjualan);

        // F-12 bagian 2: DP pre-order dipakai & pesanan ditandai diambil di transaksi yang sama.
        $uangMukaDipakai = self::JumlahkanMetode($data, $metode, JenisMetodePembayaran::UangMuka);

        if ($praPesan !== null) {
            $masalahUangMuka = [...$masalahUangMuka, ...$this->penutupPraPesan->Tandai($praPesan, $penjualan->Id, $uangMukaDipakai, $data->dibuatPada, $kasir->id)];
        }

        if ($masalahUangMuka !== []) {
            $tinjauan['UangMukaBermasalah'] = 'UangMukaBermasalah: '.implode('; ', $masalahUangMuka);
        }

        // F-12: piutang penjualan tempo di transaksi yang sama.
        if ($tempo !== null) {
            $this->piutang->Catat($idPelanggan, $penjualan->Id, $penjualan->IdOutlet, $penjualan->Nomor, $tanggalBisnis, $tempo);

        }

        // F-18: komisi staf yang melayani baris (hanya laporan, tanpa jurnal) di transaksi yang sama. Staf yang belum dikenal
        // server tidak mendapat komisi; penjualan tetap diterima + tinjauan.
        $barisKomisi = [];

        foreach ($data->baris as $indeks => $baris) {
            if ($baris->uuidKaryawan === []) {
                continue;
            }

            $h = $hasil->baris[$indeks];
            $barisKomisi[] = new DataBarisKomisi(
                $detail[$indeks]->Id,
                $baris->uuidProduk,
                $produk[$baris->uuidProduk]->uuidKategori,
                $baris->jumlah,
                $h->bruto->Kurangi($h->diskon)->Kurangi($h->diskonPesanan)->Kurangi($h->pajak->Kurangi($h->pajakEksklusif)),
                $baris->uuidKaryawan,
            );
        }

        $masalahKomisi = $this->komisi->Catat($penjualan->Id, $penjualan->IdOutlet, $tanggalBisnis, $barisKomisi);

        if ($masalahKomisi !== []) {
            $tinjauan['StafTidakDikenal'] = 'StafTidakDikenal: '.implode('; ', $masalahKomisi).', komisinya tidak dicatat';
        }

        // F-16c: pemakaian promo & kuota di transaksi yang sama.
        $diskonPerPromo = array_combine(
            array_map(fn (PromoTerpakai $p): string => $p->uuid, $promoPerangkat),
            array_map(fn (PromoTerpakai $p): Uang => $p->HitungTotal(), $promoPerangkat),
        );
        $masalahPromo = [...$masalahPromo, ...$this->pemakaianPromo->Catat($penjualan->Id, $idPelanggan, $tanggalBisnis, $diskonPerPromo)];
        // F-16c bagian 4b/4d: bagian potongan yang ditanggung pemasok menjadi klaim ke pemasok, diakui akrual
        // (Dr Piutang Klaim Promosi Pemasok, Cr HPP) di transaksi yang sama.
        $this->klaimPemasok->Catat($penjualan->Id, $penjualan->Uuid, $penjualan->Nomor, $penjualan->IdOutlet, $tanggalBisnis, $diskonPerPromo, $kasir->id);

        // F-16c bagian 2: voucher yang dipesan online menjadi terpakai di transaksi yang sama.
        if ($data->kodeVoucher !== null) {
            $masalahVoucher = $this->voucher->Pakai($data->kodeVoucher, $data->uuid, $penjualan->Id, $data->dibuatPada);

            if ($masalahVoucher !== []) {
                $tinjauan['VoucherTidakBerlaku'] = 'VoucherTidakBerlaku: '.implode('; ', $masalahVoucher);
            }
        }

        if ($masalahPromo !== []) {
            $tinjauan['PromoBerbeda'] = 'PromoBerbeda: '.implode('; ', $masalahPromo);
        }

        // F-16b: poin ditukar lebih dulu (poin dari penjualan ini tidak ikut ditukar), lalu perolehan; di transaksi yang
        // sama, idempoten per penjualan. Penjualan tetap diterima walau poin bermasalah (sudah terjadi di kasir).
        if ($data->poinDitukar > 0) {
            $masalahPoin = $idPelanggan === null
                ? ['pelanggan belum diterima server, poin tidak dipotong']
                : $this->poin->CatatPenukaran($idPelanggan, $penjualan->Id, $data->poinDitukar, $hasil->diskonPoin);

            if ($masalahPoin !== []) {
                $tinjauan['PenukaranPoin'] = 'PenukaranPoin: '.implode('; ', $masalahPoin);
            }
        }

        if ($idPelanggan !== null) {
            // F-16c bagian 4: promo poin berlipat (ditentukan server) mengalikan perolehan; pemakaiannya dicatat (kuota &
            // batas per pelanggan) hanya bila poin benar-benar diperoleh.
            $poinBerlipat = $pemeriksaanPromo->poinBerlipat;
            $diperoleh = $this->poin->CatatPerolehan($idPelanggan, $penjualan->Id, $hasil->totalAkhir, $tanggalBisnis, $poinBerlipat?->AmbilPengali());

            if ($poinBerlipat !== null && $diperoleh > 0) {
                $masalahPoinBerlipat = $this->pemakaianPromo->Catat($penjualan->Id, $idPelanggan, $tanggalBisnis, [$poinBerlipat->uuid => Uang::Nol()]);

                if ($masalahPoinBerlipat !== []) {
                    $tinjauan['PromoBerbeda'] = ($tinjauan['PromoBerbeda'] ?? 'PromoBerbeda:').' '.implode('; ', $masalahPoinBerlipat);
                }
            }
        }

        // F-10b mode cepat (bayar dulu): tiket dapur dari baris penjualan, di transaksi yang sama.
        if ($data->kirimDapur) {
            $this->kirimDapur->Jalankan(new DataKirimDapur(
                idOutlet: $penjualan->IdOutlet,
                idPesananTerbuka: null,
                idPenjualan: $penjualan->Id,
                nomorDokumen: $penjualan->Nomor,
                namaMeja: null,
                label: $penjualan->Catatan === null ? null : mb_substr($penjualan->Catatan, 0, 60),
                ronde: 1,
                dikirimPada: $data->dibuatPada,
                baris: array_map(fn (PenjualanDetail $d): DataBarisKirimDapur => new DataBarisKirimDapur(
                    $d->Uuid,
                    $d->IdProduk,
                    $d->NamaProduk,
                    (string) $d->Jumlah,
                    array_values(array_map(fn (array $p): string => $p['Nama'], $d->Pilihan ?? [])),
                    $d->Catatan,
                ), $detail),
            ));
        }
        $this->SimpanPajak($data, $penjualan, $hasil);
        // F-08 BR-08.5: pembayaran QRIS dinamis ditautkan ke tagihannya (masalah = diterima + tinjauan).
        [$tinjauanQris, $refEksternal] = $this->penautQris->Tautkan($data, $metode);
        $tinjauan += $tinjauanQris;
        $this->SimpanPembayaran($data, $penjualan, $metode, $refEksternal);

        [$tinjauanStok, $perubahanPersediaan] = $this->CatatStok($data, $penjualan, $outlet, $produk, $detail, $kasir->id);
        $tinjauan += $tinjauanStok;
        ksort($tinjauan);
        $tinjauan = array_values($tinjauan);

        // F-11: penjualan yang tiba setelah shift ditutup tetap diterima (outbox FIFO), tetapi kas shift sudah dihitung.
        if (! $shift->aktif) {
            $tinjauan[] = 'ShiftSudahDitutup: penjualan diterima setelah shift ditutup, belum masuk hitungan kas tutup shift';
        }

        $pendapatan = [];

        foreach ($data->baris as $indeks => $baris) {
            $h = $hasil->baris[$indeks];
            $peran = $produk[$baris->uuidProduk]->jenis === JenisProduk::Jasa ? PeranAkun::PendapatanJasa : PeranAkun::Penjualan;
            $pendapatan[] = [$peran, $h->bruto->Kurangi($h->pajak->Kurangi($h->pajakEksklusif))];
        }

        $pajak = [];

        foreach ($hasil->pajak as $kode => $rincian) {
            $pajak[$kode] = $rincian->jumlah;
        }

        $jurnal = $this->penyusunJurnal->Susun($penjualan, $pendapatan, $pajak, $bersih, $perubahanPersediaan);
        $adaNilai = array_filter($jurnal->baris, fn ($b): bool => ! $b->debit->BernilaiNol() || ! $b->kredit->BernilaiNol()) !== [];

        if ($adaNilai) {
            $penjualan->IdJurnal = $this->postingJurnal->Jalankan($jurnal)->idJurnal;
        }

        $penjualan->TotalHpp = Uang::Nol()->Kurangi(array_reduce($perubahanPersediaan, fn (Uang $t, Uang $u): Uang => $t->Tambah($u), Uang::Nol()))->KeString();

        if ($tinjauan !== []) {
            $penjualan->PerluTinjauan = true;
            $penjualan->AlasanTinjauan = mb_substr(implode('; ', $tinjauan), 0, self::PANJANG_ALASAN_TINJAUAN);
        }

        $penjualan->save();

        $this->riwayat->Catat(Penjualan::JENIS_DOKUMEN, $penjualan->Id, null, StatusPenjualan::Lunas->value, $kasir->id);
        $this->audit->Catat('penjualan.terima', $penjualan, nilaiBaru: [
            'Nomor' => $penjualan->Nomor,
            'TotalAkhir' => $penjualan->TotalAkhir,
            'TotalDiskon' => $penjualan->TotalDiskon,
            'DisetujuiOleh' => $penyetuju?->nama,
            'PerluTinjauan' => $penjualan->PerluTinjauan,
        ], idPengguna: $kasir->id);

        // F-14a: ringkasan laporan harian dihitung ulang di antrean setelah commit (efek non-kritis, aturan #10).
        PenjualanDiterima::dispatch($penjualan->IdTenant, $penjualan->IdOutlet, $penjualan->TanggalBisnis->toDateString(), $penjualan->Id);

        return StatusItemSinkron::Diterima;
    }

    /** BR-07.1: `INV/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ≥4}`; YYMMDD = tanggal bisnis atau tanggal lokal outlet. */
    private function PeriksaNomor(DataPenjualanPos $data, DataOutletPenjualan $outlet, CarbonImmutable $tanggalBisnis): void
    {
        $pola = '#^INV/'.preg_quote($outlet->kodeOutlet, '#').'/(\d{6})/'.preg_quote($outlet->kodePerangkat, '#').'-\d{4,}$#';
        $tanggalBoleh = [$tanggalBisnis->format('ymd'), $data->dibuatPada->setTimezone($outlet->zonaWaktu)->format('ymd')];

        if (preg_match($pola, $data->nomor, $cocok) !== 1 || ! in_array($cocok[1], $tanggalBoleh, true)) {
            throw new PelanggaranAturanBisnis(
                'NomorTidakValid',
                "Nomor {$data->nomor} tidak sesuai format INV/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001.",
                'Nomor',
                detail: ['Contoh' => "INV/{$outlet->kodeOutlet}/{$tanggalBoleh[0]}/{$outlet->kodePerangkat}-0001"],
            );
        }

        if (Penjualan::query()->where('Nomor', $data->nomor)->exists()) {
            throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor {$data->nomor} sudah dipakai penjualan lain.", 'Nomor', 409);
        }
    }

    /**
     * @return array<string, DataProdukPenjualan> kunci = Uuid produk
     */
    private function AmbilProduk(DataPenjualanPos $data): array
    {
        $produk = $this->komposisi->AmbilProduk(array_values(array_map(fn (DataBarisPenjualanPos $b): string => $b->uuidProduk, $data->baris)));

        foreach ($data->baris as $indeks => $baris) {
            $p = $produk[$baris->uuidProduk] ?? null;
            $bidang = "Baris.{$indeks}.UuidProduk";

            if ($p === null) {
                throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk pada baris ke-'.($indeks + 1).' tidak ditemukan.', $bidang);
            }

            if (in_array($p->jenis, self::JENIS_TIDAK_BISA_DIJUAL, true)) {
                throw new PelanggaranAturanBisnis('ProdukTidakBisaDijual', "Produk {$p->nama} berjenis {$p->jenis->AmbilLabel()} belum bisa dijual di POS.", $bidang);
            }

            if ($p->pelacakan !== PelacakanProduk::Tidak) {
                throw new PelanggaranAturanBisnis('PelacakanBelumDidukung', "Produk {$p->nama} memakai {$p->pelacakan->AmbilLabel()}; penjualan produk berpelacakan belum didukung.", $bidang);
            }

            if ($baris->uuidProdukSatuan !== null && ! isset($p->satuan[$baris->uuidProdukSatuan])) {
                throw new PelanggaranAturanBisnis('SatuanTidakDikenal', "Satuan jual {$p->nama} tidak ditemukan.", "Baris.{$indeks}.UuidProdukSatuan");
            }
        }

        return $produk;
    }

    /** CLAUDE.md #12: setiap pajak snapshot = TarifPajak terbit yang berlaku pada tanggal bisnis di wilayah outlet. */
    private function PeriksaTarifPajak(DataPenjualanPos $data, DataOutletPenjualan $outlet, CarbonImmutable $tanggalBisnis): void
    {
        foreach ($data->pajak as $indeks => $pajak) {
            $tarif = $this->tarifBerlaku->CariDataOutlet($pajak->kode, $outlet->kodeKota, $tanggalBisnis);
            $cocok = $tarif !== null
                && BigDecimal::of($tarif->tarif)->isEqualTo($pajak->tarif)
                && $tarif->pengaliDppPembilang * $pajak->pengaliDppPenyebut === $tarif->pengaliDppPenyebut * $pajak->pengaliDppPembilang;

            if (! $cocok) {
                throw new PelanggaranAturanBisnis(
                    'TarifPajakTidakSah',
                    "Tarif {$pajak->kode} {$pajak->tarif}% tidak sesuai tarif yang berlaku pada {$tanggalBisnis->toDateString()}. Perbarui data aplikasi kasir.",
                    "Pajak.{$indeks}",
                    detail: ['Kode' => $pajak->kode, 'TarifBerlaku' => $tarif?->tarif],
                );
            }
        }
    }

    /**
     * @return array<string, MetodePembayaran> kunci = Uuid metode
     */
    private function AmbilMetode(DataPenjualanPos $data): array
    {
        $uuid = array_values(array_unique(array_map(fn ($b): string => $b->uuidMetodePembayaran, $data->pembayaran)));
        $metode = MetodePembayaran::query()->whereIn('Uuid', $uuid)->get()->keyBy('Uuid')->all();

        foreach ($data->pembayaran as $indeks => $bayar) {
            $m = $metode[$bayar->uuidMetodePembayaran] ?? null;

            if (! $m instanceof MetodePembayaran) {
                throw new PelanggaranAturanBisnis('MetodeBayarTidakDikenal', 'Metode pembayaran tidak ditemukan.', "Pembayaran.{$indeks}.UuidMetodePembayaran");
            }

            // Metode yang dinonaktifkan setelah transaksi offline tetap diterima (tidak memeriksa `Aktif`).
            if (! $m->Jenis->CekDidukungPos()) {
                throw new PelanggaranAturanBisnis('MetodeBayarBelumDidukung', "Pembayaran {$m->Jenis->AmbilLabel()} belum didukung di aplikasi kasir.", "Pembayaran.{$indeks}.UuidMetodePembayaran");
            }
        }

        return $metode;
    }

    /**
     * Hitung ulang dengan mesin. Hasil: [hasil akhir (dengan promo perangkat), hasil tanpa promo (untuk batas diskon
     * manual), masukan tanpa promo, promo perangkat].
     *
     * @param  array<string, MetodePembayaran>  $metode
     * @return array{0: HasilKalkulasi, 1: HasilKalkulasi, 2: DataKalkulasi, 3: list<PromoTerpakai>}
     */
    private function HitungUlang(DataPenjualanPos $data, array $metode): array
    {
        foreach ($data->baris as $indeks => $baris) {
            $totalPilihan = array_reduce($baris->pilihan, fn (Uang $t, array $p): Uang => $t->Tambah(Uang::Dari($p['Harga'])), Uang::Nol());

            if ($baris->pilihan !== [] && ! $totalPilihan->SamaDengan($baris->hargaPilihan)) {
                throw self::GalatHitungan("Baris.{$indeks}.HargaPilihan", $baris->hargaPilihan, $totalPilihan);
            }
        }

        // F-16c: promo perangkat → potongan nominal per indeks baris (Uuid baris wajib ada di dokumen).
        $indeksBaris = array_flip(array_map(fn (DataBarisPenjualanPos $b): string => $b->uuid, $data->baris));
        $promoPerangkat = [];

        foreach ($data->promo as $p) {
            $diskonBaris = [];

            foreach ($p->diskonBaris as $uuidBaris => $jumlah) {
                if (! isset($indeksBaris[$uuidBaris])) {
                    throw new PelanggaranAturanBisnis('DataTidakValid', "Promo {$p->kode} merujuk baris yang tidak ada.", 'Promo');
                }

                $diskonBaris[$indeksBaris[$uuidBaris]] = $jumlah;
            }

            ksort($diskonBaris);
            $promoPerangkat[] = new PromoTerpakai($p->uuidPromo, $p->kode, $diskonBaris, $p->diskonPesanan);
        }

        try {
            $dasar = new DataKalkulasi(
                hargaTermasukPajak: $data->hargaTermasukPajak,
                baris: array_values(array_map(fn (DataBarisPenjualanPos $b): DataBarisKalkulasi => new DataBarisKalkulasi(
                    $b->jumlah,
                    $b->hargaSatuan,
                    $b->hargaPilihan,
                    $b->hargaTermasukPajak,
                    $b->kodePajak,
                    $b->diskonManual === null ? [] : [$b->diskonManual->KePotongan()],
                ), $data->baris)),
                pajak: array_values(array_map(fn (DataPajakPenjualanPos $p): DataPajakKalkulasi => new DataPajakKalkulasi(
                    $p->kode,
                    $p->tarif,
                    $p->dasarPengenaan,
                    $p->pengaliDppPembilang,
                    $p->pengaliDppPenyebut,
                ), $data->pajak)),
                persenBiayaLayanan: $data->persenBiayaLayanan,
                pembulatanTunai: $data->pembulatanTunai,
                potonganPesanan: $data->diskonManualPesanan === null ? [] : [$data->diskonManualPesanan->KePotongan()],
                tukarPoin: $data->nilaiTukarPoin,
                pembayaran: array_values(array_map(fn ($b): DataPembayaranKalkulasi => new DataPembayaranKalkulasi(
                    $metode[$b->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::Tunai,
                    $b->jumlah,
                ), $data->pembayaran)),
            );
            $hasilDasar = $this->mesin->Hitung($dasar);
            $hasil = $this->mesin->Hitung(MesinPromo::SusunData($dasar, $promoPerangkat));
        } catch (InvalidArgumentException $galat) {
            throw new PelanggaranAturanBisnis('DataTidakValid', 'Data penjualan tidak bisa dihitung: '.$galat->getMessage(), 'Baris');
        }

        // F-16b: nilai tukar poin tidak boleh terpotong batas sisa subtotal (perangkat wajib membatasi lebih dulu).
        if ($data->nilaiTukarPoin !== null && ! $hasil->diskonPoin->SamaDengan($data->nilaiTukarPoin)) {
            throw self::GalatHitungan('TukarPoin.Nilai', $data->nilaiTukarPoin, $hasil->diskonPoin);
        }

        $r = $data->ringkasan;

        foreach ([
            'Subtotal' => [$r->subtotal, $hasil->subtotal],
            'TotalPajak' => [$r->totalPajak, $hasil->totalPajak],
            'Pembulatan' => [$r->pembulatan, $hasil->pembulatan],
            'TotalAkhir' => [$r->totalAkhir, $hasil->totalAkhir],
        ] as $bidang => [$perangkat, $server]) {
            if (! $perangkat->SamaDengan($server)) {
                throw self::GalatHitungan("Ringkasan.{$bidang}", $perangkat, $server);
            }
        }

        return [$hasil, $hasilDasar, $dasar, $promoPerangkat];
    }

    /**
     * Pasangan [dasar, diskon manual] per baris ber-diskon manual dan untuk diskon pesanan (BR-07.3).
     *
     * @return list<array{0: Uang, 1: Uang}>
     */
    private function KumpulkanDiskon(DataPenjualanPos $data, HasilKalkulasi $hasil): array
    {
        $diskon = [];

        foreach ($data->baris as $indeks => $baris) {
            if ($baris->diskonManual !== null) {
                $diskon[] = [$hasil->baris[$indeks]->bruto, $hasil->baris[$indeks]->diskon];
            }
        }

        if ($data->diskonManualPesanan !== null) {
            // Diskon poin (F-16b) bukan diskon manual: tidak ikut batas diskon kasir.
            $diskon[] = [$hasil->subtotal, $hasil->diskonPesanan->Kurangi($hasil->diskonPoin)];
        }

        return $diskon;
    }

    /**
     * Σ pembayaran berjenis [jenis] (F-12 bagian 2: uang muka pre-order yang dipakai).
     *
     * @param  array<string, MetodePembayaran>  $metode
     */
    private static function JumlahkanMetode(DataPenjualanPos $data, array $metode, JenisMetodePembayaran $jenis): Uang
    {
        $total = Uang::Nol();

        foreach ($data->pembayaran as $bayar) {
            if ($metode[$bayar->uuidMetodePembayaran]->Jenis === $jenis) {
                $total = $total->Tambah($bayar->jumlah);
            }
        }

        return $total;
    }

    /**
     * F-12: jumlah pembayaran tempo (null = bukan penjualan tempo).
     *
     * @param  array<string, MetodePembayaran>  $metode
     */
    private function AmbilJumlahTempo(DataPenjualanPos $data, array $metode): ?Uang
    {
        foreach ($data->pembayaran as $bayar) {
            if ($metode[$bayar->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::Tempo) {
                return $bayar->jumlah;
            }
        }

        return null;
    }

    /**
     * Pembayaran fase 1: maksimal satu baris tunai, non-tunai tidak melebihi total, Σ pembayaran ≥ TotalAkhir, dan
     * kembalian sama dengan hitungan perangkat.
     *
     * @param  array<string, MetodePembayaran>  $metode
     * @return array{0: Uang, 1: list<array{0: MetodePembayaran, 1: Uang}>} [total dibayar, nilai bersih per pembayaran]
     */
    private function PeriksaPembayaran(DataPenjualanPos $data, array $metode, HasilKalkulasi $hasil): array
    {
        $jumlahTunai = 0;
        $nonTunai = Uang::Nol();
        $total = Uang::Nol();

        foreach ($data->pembayaran as $bayar) {
            $total = $total->Tambah($bayar->jumlah);

            if ($metode[$bayar->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::Tunai) {
                $jumlahTunai++;
            } else {
                $nonTunai = $nonTunai->Tambah($bayar->jumlah);
            }
        }

        $jumlahTempo = count(array_filter($data->pembayaran, fn ($b): bool => $metode[$b->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::Tempo));
        $jumlahUangMuka = count(array_filter($data->pembayaran, fn ($b): bool => $metode[$b->uuidMetodePembayaran]->Jenis === JenisMetodePembayaran::UangMuka));

        // F-12 bagian 2: DP pre-order hanya dipakai sekali per penjualan dan wajib merujuk pesanannya.
        if ($jumlahUangMuka > 1) {
            throw new PelanggaranAturanBisnis('PembayaranTidakValid', 'Satu penjualan hanya boleh memakai uang muka satu kali.', 'Pembayaran');
        }

        if ($jumlahUangMuka === 1 && $data->uuidPesananPenjualan === null) {
            throw new PelanggaranAturanBisnis('UangMukaTanpaPesanan', 'Pembayaran uang muka wajib merujuk pre-order yang diambil.', 'UuidPesananPenjualan');
        }

        if ($jumlahTempo > 1) {
            throw new PelanggaranAturanBisnis('PembayaranTidakValid', 'Satu penjualan hanya boleh punya satu pembayaran tempo.', 'Pembayaran');
        }

        if ($jumlahTempo === 1 && $data->uuidPelanggan === null) {
            throw new PelanggaranAturanBisnis('TempoTanpaPelanggan', 'Pembayaran tempo wajib memilih pelanggan.', 'UuidPelanggan');
        }

        if ($jumlahTunai > 1) {
            throw new PelanggaranAturanBisnis('PembayaranTidakValid', 'Satu penjualan hanya boleh punya satu pembayaran tunai.', 'Pembayaran');
        }

        if ($nonTunai->Bandingkan($hasil->totalAkhir) > 0) {
            throw new PelanggaranAturanBisnis('PembayaranTidakValid', "Pembayaran non-tunai ({$nonTunai->FormatRupiah()}) melebihi total {$hasil->totalAkhir->FormatRupiah()}.", 'Pembayaran');
        }

        if ($total->Bandingkan($hasil->totalAkhir) < 0) {
            throw new PelanggaranAturanBisnis('PembayaranKurang', "Pembayaran {$total->FormatRupiah()} kurang dari total {$hasil->totalAkhir->FormatRupiah()}.", 'Pembayaran', 422, [
                'TotalAkhir' => $hasil->totalAkhir->KeString(),
                'TotalDibayar' => $total->KeString(),
            ]);
        }

        $kembalian = $hasil->kembalian ?? Uang::Nol();

        if (! $kembalian->SamaDengan($data->ringkasan->kembalian)) {
            throw self::GalatHitungan('Ringkasan.Kembalian', $data->ringkasan->kembalian, $kembalian);
        }

        $bersih = [];

        foreach ($data->pembayaran as $bayar) {
            $m = $metode[$bayar->uuidMetodePembayaran];
            $bersih[] = [$m, $m->Jenis === JenisMetodePembayaran::Tunai ? $bayar->jumlah->Kurangi($kembalian) : $bayar->jumlah];
        }

        return [$total, $bersih];
    }

    private function SimpanPenjualan(
        DataPenjualanPos $data,
        int $idShift,
        DataOutletPenjualan $outlet,
        DataAnggotaOutlet $kasir,
        ?DataAnggotaOutlet $penyetuju,
        CarbonImmutable $tanggalBisnis,
        HasilKalkulasi $hasil,
        Uang $totalDibayar,
        ?int $idPesananTerbuka,
        ?int $idPelanggan,
        ?int $idPenyetujuTempo = null,
        ?int $idPesananPenjualan = null,
    ): Penjualan {
        return Penjualan::query()->create([
            'Uuid' => $data->uuid,
            'IdOutlet' => $outlet->idOutlet,
            'IdShift' => $idShift,
            'IdPerangkat' => $data->idPerangkat,
            'IdPesananTerbuka' => $idPesananTerbuka,
            'IdPesananPenjualan' => $idPesananPenjualan,
            'IdPelanggan' => $idPelanggan,
            'Nomor' => $data->nomor,
            'Kanal' => $data->kanal,
            'Status' => StatusPenjualan::Lunas,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'IdPengguna' => $kasir->id,
            'IdPenyetujuDiskon' => $penyetuju?->id,
            'IdPenyetujuTempo' => $idPenyetujuTempo,
            'HargaTermasukPajak' => $data->hargaTermasukPajak,
            'PersenBiayaLayanan' => (string) $data->persenBiayaLayanan->toScale(2),
            'PembulatanTunai' => $data->pembulatanTunai === null ? null : ['Kelipatan' => $data->pembulatanTunai->kelipatan, 'Arah' => $data->pembulatanTunai->arah->value],
            'Subtotal' => $hasil->subtotal->KeString(),
            'DiskonBaris' => $hasil->diskonBaris->KeString(),
            'DiskonPesanan' => $hasil->diskonPesanan->KeString(),
            'PoinDitukar' => $data->poinDitukar,
            'DiskonPoin' => $hasil->diskonPoin->KeString(),
            'TotalDiskon' => $hasil->totalDiskon->KeString(),
            'BiayaLayanan' => $hasil->biayaLayanan->KeString(),
            'TotalPajak' => $hasil->totalPajak->KeString(),
            'TotalPajakEksklusif' => $hasil->totalPajakEksklusif->KeString(),
            'Pembulatan' => $hasil->pembulatan->KeString(),
            'TotalAkhir' => $hasil->totalAkhir->KeString(),
            'TotalDibayar' => $totalDibayar->KeString(),
            'Kembalian' => ($hasil->kembalian ?? Uang::Nol())->KeString(),
            'TotalHpp' => '0.00',
            'Catatan' => $data->catatan === null ? null : mb_substr($data->catatan, 0, 500),
            'PerluTinjauan' => false,
            'DibuatOfflinePada' => $data->dibuatPada,
            'DiterimaPada' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, DataProdukPenjualan>  $produk
     * @return list<PenjualanDetail> urutan sama dengan baris masukan
     */
    private function SimpanDetail(DataPenjualanPos $data, Penjualan $penjualan, array $produk, HasilKalkulasi $hasil): array
    {
        $pajakDokumen = [];

        foreach ($data->pajak as $p) {
            $pajakDokumen[$p->kode] = $p->KeLarik();
        }

        $detail = [];

        foreach ($data->baris as $indeks => $baris) {
            $p = $produk[$baris->uuidProduk];
            [$idSatuan, $konversi] = $baris->uuidProdukSatuan === null
                ? [$p->idSatuanDasar, '1']
                : [$p->satuan[$baris->uuidProdukSatuan]['IdSatuan'], $p->satuan[$baris->uuidProdukSatuan]['KonversiKeDasar']];
            $kode = $baris->kodePajak ?? array_keys($pajakDokumen);
            $h = $hasil->baris[$indeks];

            $detail[] = PenjualanDetail::query()->create([
                'Uuid' => $baris->uuid,
                'IdPenjualan' => $penjualan->Id,
                'Urutan' => $indeks + 1,
                'IdProduk' => $p->id,
                'NamaProduk' => mb_substr($p->nama, 0, 255),
                'IdSatuan' => $idSatuan,
                'KonversiKeDasar' => $konversi,
                'Jumlah' => $baris->jumlah->KeString(),
                'JumlahDasar' => $baris->jumlah->Kali($konversi)->KeString(),
                'HargaSatuan' => $baris->hargaSatuan->KeString(),
                'HargaPilihan' => $baris->hargaPilihan->KeString(),
                'Pilihan' => $baris->pilihan === [] ? null : $baris->pilihan,
                'HargaTermasukPajak' => $baris->hargaTermasukPajak ?? $data->hargaTermasukPajak,
                'SnapshotPajak' => array_values(array_filter(array_map(fn (string $k): ?array => $pajakDokumen[$k] ?? null, $kode))),
                'DiskonManual' => $baris->diskonManual?->KeLarik(),
                'Bruto' => $h->bruto->KeString(),
                'JumlahDiskon' => $h->diskon->KeString(),
                'JumlahDiskonPesanan' => $h->diskonPesanan->KeString(),
                'BiayaLayanan' => $h->biayaLayanan->KeString(),
                'JumlahPajak' => $h->pajak->KeString(),
                'PajakEksklusif' => $h->pajakEksklusif->KeString(),
                'TotalBaris' => $h->totalBaris->KeString(),
                'HppSatuan' => '0',
                'TotalHpp' => '0.00',
                'Catatan' => $baris->catatan === null ? null : mb_substr($baris->catatan, 0, 255),
            ]);
        }

        return $detail;
    }

    private function SimpanPajak(DataPenjualanPos $data, Penjualan $penjualan, HasilKalkulasi $hasil): void
    {
        foreach ($data->pajak as $p) {
            $rincian = $hasil->pajak[$p->kode] ?? new HasilPajakKalkulasi($p->kode, Uang::Nol(), Uang::Nol());

            PenjualanPajak::query()->create([
                'IdPenjualan' => $penjualan->Id,
                'KodeJenisPajak' => $p->kode,
                'Tarif' => (string) $p->tarif->toScale(6),
                'PengaliDppPembilang' => $p->pengaliDppPembilang,
                'PengaliDppPenyebut' => $p->pengaliDppPenyebut,
                'DasarPengenaan' => $p->dasarPengenaan,
                'Dpp' => $rincian->dpp->KeString(),
                'Jumlah' => $rincian->jumlah->KeString(),
            ]);
        }
    }

    /**
     * @param  array<string, MetodePembayaran>  $metode
     * @param  array<string, string>  $refEksternal  Uuid pembayaran → nomor pesanan gerbang (F-08 QRIS dinamis)
     */
    private function SimpanPembayaran(DataPenjualanPos $data, Penjualan $penjualan, array $metode, array $refEksternal): void
    {
        foreach ($data->pembayaran as $indeks => $bayar) {
            $m = $metode[$bayar->uuidMetodePembayaran];

            PenjualanPembayaran::query()->create([
                'Uuid' => $bayar->uuid,
                'IdPenjualan' => $penjualan->Id,
                'Urutan' => $indeks + 1,
                'IdMetodePembayaran' => $m->Id,
                'JenisMetode' => $m->Jenis,
                'NamaMetode' => mb_substr($m->Nama, 0, 100),
                'Jumlah' => $bayar->jumlah->KeString(),
                'Status' => PenjualanPembayaran::STATUS_DITERIMA,
                'Referensi' => $bayar->referensi,
                'RefEksternal' => $refEksternal[$bayar->uuid] ?? null,
                'DibayarPada' => $data->dibuatPada,
            ]);
        }
    }

    /**
     * Mutasi stok `Penjualan` dari lokasi stok Toko outlet (produk berstok, bahan resep, komponen paket, bahan pilihan;
     * satuan dasar), lalu HPP per baris dari hasil mutasi.
     *
     * @param  array<string, DataProdukPenjualan>  $produk
     * @param  list<PenjualanDetail>  $detail
     * @return array{0: array<string, string>, 1: array<string, Uang>} [kode → alasan tinjauan, perubahan nilai per peran akun persediaan]
     */
    private function CatatStok(DataPenjualanPos $data, Penjualan $penjualan, DataOutletPenjualan $outlet, array $produk, array $detail, int $idKasir): array
    {
        $kebutuhan = $this->komposisi->AmbilKebutuhanStok(array_values(array_map(fn (DataProdukPenjualan $p): int => $p->id, $produk)));
        $uuidPilihan = [];

        foreach ($data->baris as $baris) {
            foreach ($baris->pilihan as $p) {
                $uuidPilihan[] = $p['UuidPilihan'];
            }
        }

        $pilihan = $this->komposisi->AmbilPilihan(array_values(array_unique($uuidPilihan)));
        $tinjauan = [];
        $barisMutasi = [];
        $infoPerKunci = [];
        $indeksPerKunci = [];

        foreach ($data->baris as $indeks => $baris) {
            $jumlahDasar = Kuantitas::Dari($detail[$indeks]->JumlahDasar);
            $daftar = [];

            foreach ($kebutuhan[$produk[$baris->uuidProduk]->id] ?? [] as $k) {
                $daftar[] = [$k, $jumlahDasar];
            }

            foreach ($baris->pilihan as $p) {
                $dataPilihan = $pilihan[$p['UuidPilihan']] ?? null;

                if ($dataPilihan === null) {
                    $tinjauan['PilihanTidakDikenal'] = "PilihanTidakDikenal: pilihan \"{$p['Nama']}\" sudah dihapus, bahannya tidak dikurangi";

                    continue;
                }

                if ($dataPilihan->bahan !== null) {
                    $daftar[] = [$dataPilihan->bahan, $baris->jumlah];
                }
            }

            $urutan = 0;

            foreach ($daftar as [$k, $dasar]) {
                /** @var DataKebutuhanStok $k */
                $this->PastikanBahanBisaDikurangi($k, $indeks);

                if ($k->dihapus) {
                    // Produk hanya bisa dihapus bila belum pernah dipakai (BR-03.2): tidak punya stok untuk dikurangi.
                    $tinjauan['ProdukDihapus'] = "ProdukDihapus: {$k->nama} sudah dihapus, stok tidak dikurangi";

                    continue;
                }

                $jumlah = BigDecimal::of($dasar->KeString())->multipliedBy($k->pembilang)->dividedBy($k->penyebut, Kuantitas::SKALA, RoundingMode::HalfUp);

                if ($jumlah->isZero()) {
                    continue;
                }

                $urutan++;
                $kunci = $baris->uuid.'/'.$urutan;
                $barisMutasi[] = new DataBarisMutasi(
                    kunciBaris: $kunci,
                    idProduk: $k->idProduk,
                    idGudang: 0,
                    jenisMutasi: JenisMutasi::Penjualan,
                    jumlah: Kuantitas::Dari($jumlah->negated()),
                    modeNilai: ModeNilaiMutasi::Berjalan,
                    idReferensiDetail: $detail[$indeks]->Id,
                );
                $infoPerKunci[$kunci] = $k;
                $indeksPerKunci[$kunci] = $indeks;
            }
        }

        if ($barisMutasi === []) {
            return [$tinjauan, []];
        }

        if ($outlet->idGudangToko === null) {
            throw new PelanggaranAturanBisnis('LokasiStokTidakAda', "Outlet {$outlet->namaOutlet} belum punya lokasi stok Toko untuk mengurangi stok penjualan.", 'Baris');
        }

        $hasil = $this->catatMutasi->Jalankan(new DataDokumenMutasi(
            jenisReferensi: JenisReferensiMutasi::Penjualan,
            idReferensi: $penjualan->Id,
            uuidReferensi: $penjualan->Uuid,
            nomorReferensi: $penjualan->Nomor,
            tanggalBisnis: CarbonImmutable::parse($penjualan->TanggalBisnis->toDateString()),
            idPengguna: $idKasir,
            idPerangkat: $data->idPerangkat,
            baris: array_map(fn (DataBarisMutasi $b): DataBarisMutasi => new DataBarisMutasi(
                kunciBaris: $b->kunciBaris,
                idProduk: $b->idProduk,
                idGudang: (int) $outlet->idGudangToko,
                jenisMutasi: $b->jenisMutasi,
                jumlah: $b->jumlah,
                modeNilai: $b->modeNilai,
                idReferensiDetail: $b->idReferensiDetail,
            ), $barisMutasi),
            abaikanBatasMinus: true,
        ));

        $this->IsiHppBaris($hasil, $detail, $indeksPerKunci);

        $kurang = [];

        foreach ($hasil->AmbilBarisStokTidakCukup() as $b) {
            $kurang[$b->idProduk] = $infoPerKunci[$b->kunciBaris]->nama.' (sisa '.PemeriksaStokMinus::FormatJumlah($b->saldoSetelah).')';
        }

        if ($kurang !== []) {
            $tinjauan['StokTidakCukup'] = 'StokTidakCukup: '.implode(', ', $kurang);
        }

        $perubahan = [];

        foreach ($hasil->baris as $kunci => $b) {
            $peran = $this->petaAkunPersediaan->UntukJenis($infoPerKunci[$kunci]->jenis)->value;
            $perubahan[$peran] = ($perubahan[$peran] ?? Uang::Nol())->Tambah($b->totalHpp);
        }

        return [$tinjauan, $perubahan];
    }

    private function PastikanBahanBisaDikurangi(DataKebutuhanStok $k, int $indeks): void
    {
        if ($k->jenis === JenisProduk::Konsinyasi) {
            throw new PelanggaranAturanBisnis('ProdukTidakBisaDijual', "{$k->nama} ({$k->jalur}) adalah barang konsinyasi yang belum bisa dijual di POS.", "Baris.{$indeks}.UuidProduk");
        }

        if ($k->pelacakan !== PelacakanProduk::Tidak) {
            throw new PelanggaranAturanBisnis('PelacakanBelumDidukung', "{$k->nama} ({$k->jalur}) memakai {$k->pelacakan->AmbilLabel()}; penjualan yang mengurangi stok berpelacakan belum didukung.", "Baris.{$indeks}.UuidProduk");
        }
    }

    /**
     * HPP baris = −Σ TotalHpp mutasi baris itu; HPP per satuan jual = TotalHpp ÷ Jumlah (skala 6).
     *
     * @param  list<PenjualanDetail>  $detail
     * @param  array<string, int>  $indeksPerKunci
     */
    private function IsiHppBaris(HasilCatatMutasi $hasil, array $detail, array $indeksPerKunci): void
    {
        $total = [];

        foreach ($hasil->baris as $kunci => $b) {
            $indeks = $indeksPerKunci[$kunci];
            $total[$indeks] = ($total[$indeks] ?? Uang::Nol())->Kurangi($b->totalHpp);
        }

        foreach ($total as $indeks => $hpp) {
            $d = $detail[$indeks];
            $d->TotalHpp = $hpp->KeString();
            $d->HppSatuan = (string) BigDecimal::of($hpp->KeString())->dividedBy($d->Jumlah, 6, RoundingMode::HalfUp);
            $d->save();
        }
    }

    private static function GalatHitungan(string $bidang, Uang $perangkat, Uang $server): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis(
            'HitunganTidakCocok',
            "Hitungan {$bidang} di perangkat ({$perangkat->FormatRupiah()}) berbeda dengan hitungan server ({$server->FormatRupiah()}). Perbarui aplikasi kasir.",
            $bidang,
            422,
            ['Perangkat' => $perangkat->KeString(), 'Server' => $server->KeString()],
        );
    }
}
