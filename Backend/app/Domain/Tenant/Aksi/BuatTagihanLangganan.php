<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Tenant\Enum\JenisTagihanLangganan;
use App\Domain\Tenant\Enum\SiklusTagihan;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Kueri\HargaPaketBerlaku;
use App\Domain\Tenant\Kueri\RekeningTujuanPlatform;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Layanan\KalkulatorTagihanLangganan;
use App\Domain\Tenant\Layanan\PenghitungPeriodeLangganan;
use App\Domain\Tenant\Layanan\PenomorTagihanLangganan;
use App\Domain\Tenant\Model\KuponLangganan;
use App\Domain\Tenant\Model\KuponLanggananPemakaian;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\TagihanLangganan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owner membuat tagihan langganan untuk upgrade dari Trial/Gratis, aktivasi ulang, atau perpanjangan (P-08, F-19
 * Fase 0: tagihan manual). Dalam satu transaksi dengan kunci `Langganan` tenant:
 * - hanya satu tagihan terbuka per tenant (klik ganda / dua tab tidak membuat tagihan ganda);
 * - harga dari `HargaPaketBerlaku` (grandfathering BR-P04.1), PPN dari `TarifPajak` Ppn terbit (tanpa hard-code);
 * - kupon dicatat di `KuponLanggananPemakaian` dengan kunci baris kupon agar kuota tidak terlampaui (BR-P04.7);
 * - nomor tagihan urut tanpa celah (BR-P08.1).
 * Ganti paket saat langganan Aktif butuh proration (F-19) dan ditunda: Owner diminta memperpanjang paket berjalan.
 */
final class BuatTagihanLangganan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly HargaPaketBerlaku $hargaBerlaku,
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly RekeningTujuanPlatform $rekening,
        private readonly KalkulatorTagihanLangganan $kalkulator,
        private readonly PenomorTagihanLangganan $penomor,
        private readonly TagihanLanggananTenant $tagihanTenant,
    ) {}

    public function Jalankan(int $idPengguna, string $kodePaket, SiklusTagihan $siklus, ?string $kodeKupon = null): TagihanLangganan
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $idPengguna, $kodePaket, $siklus, $kodeKupon): TagihanLangganan {
            $langganan = Langganan::query()->where('IdTenant', $idTenant)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Data langganan usaha ini tidak ditemukan. Hubungi tim kami.');

            if ($langganan->Status === StatusLangganan::Berhenti) {
                throw new PelanggaranAturanBisnis('LanggananBerhenti', 'Langganan usaha ini sudah berhenti. Hubungi tim kami untuk mengaktifkan kembali.');
            }

            $terbuka = TagihanLangganan::query()->whereIn('Status', StatusTagihanLangganan::NilaiTerbuka())->first();

            if ($terbuka !== null) {
                throw new PelanggaranAturanBisnis(
                    'TagihanMasihTerbuka',
                    "Masih ada tagihan {$terbuka->Nomor} yang belum dibayar. Bayar atau batalkan tagihan itu dulu.",
                );
            }

            $paket = $this->TentukanPaket($langganan, $kodePaket);
            $jenis = $this->TentukanJenis($langganan, $paket);
            $sekarang = CarbonImmutable::now();

            $harga = $this->hargaBerlaku->Cari($paket->Id, $sekarang, $jenis === JenisTagihanLangganan::Perpanjangan ? $this->tagihanTenant->AmbilMulaiLanggananPaket($paket->Id) : null)
                ?? throw new PelanggaranAturanBisnis('HargaBelumTersedia', "Harga paket {$paket->Nama} belum tersedia. Hubungi tim kami.", 'KodePaket');
            $subtotal = $siklus === SiklusTagihan::Tahunan ? $harga->AmbilHargaTahunan() : $harga->AmbilHargaBulanan();

            if ($subtotal->Bandingkan(Uang::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('PaketTanpaBiaya', "Paket {$paket->Nama} tidak memerlukan tagihan.", 'KodePaket');
            }

            if ($this->rekening->Ambil() === []) {
                throw new PelanggaranAturanBisnis('RekeningBelumDiatur', 'Pembayaran transfer belum dibuka karena rekening tujuan belum diatur. Hubungi tim kami.');
            }

            $tarif = null;

            if ((bool) config('tagihan.PlatformPkp')) {
                $tarif = $this->tarifBerlaku->Cari('Ppn', null, $sekarang)
                    ?? throw new PelanggaranAturanBisnis('TarifPpnBelumTerbit', 'Tagihan belum bisa dibuat karena tarif PPN belum diterbitkan. Hubungi tim kami.');
            }

            $jumlahBulan = PenghitungPeriodeLangganan::JumlahBulan($siklus);
            $kupon = $kodeKupon === null || trim($kodeKupon) === '' ? null : $this->CariKupon($idTenant, strtoupper(trim($kodeKupon)), $paket);
            $bulanDiskon = $kupon === null ? 0 : min($jumlahBulan, $kupon->DurasiBulan - $this->HitungBulanTerpakai($idTenant, $kupon));

            $rincian = $this->kalkulator->Hitung(
                subtotal: $subtotal,
                jumlahBulan: $jumlahBulan,
                jenisKupon: $kupon?->Jenis,
                nilaiKupon: $kupon?->Nilai,
                bulanDiskon: $bulanDiskon,
                tarifPersen: $tarif?->Tarif,
                pengaliDppPembilang: $tarif->PengaliDppPembilang ?? 1,
                pengaliDppPenyebut: $tarif->PengaliDppPenyebut ?? 1,
            );

            // Tagihan Rp 0 (kupon 100%) butuh aktivasi tanpa transfer; ditunda sampai alur itu dirancang.
            if ($rincian->total->BernilaiNol()) {
                throw new PelanggaranAturanBisnis('TotalNol', 'Tagihan dengan kupon ini bernilai Rp 0 dan belum bisa diproses otomatis. Hubungi tim kami.', 'KodeKupon');
            }

            $tagihan = TagihanLangganan::query()->create([
                'IdTenant' => $idTenant,
                'Nomor' => $this->penomor->Ambil($sekarang),
                'Jenis' => $jenis,
                'Status' => StatusTagihanLangganan::Terbit,
                'IdPaket' => $paket->Id,
                'IdHargaPaket' => $harga->Id,
                'Siklus' => $siklus,
                'JumlahBulan' => $jumlahBulan,
                'Subtotal' => $rincian->subtotal->KeString(),
                'IdKuponLangganan' => $kupon?->Id,
                'KodeKupon' => $kupon?->Kode,
                'Diskon' => $rincian->diskon->KeString(),
                'IdTarifPajak' => $tarif?->Id,
                'TarifPpn' => $tarif->Tarif ?? '0',
                'PengaliDppPembilang' => $tarif->PengaliDppPembilang ?? 1,
                'PengaliDppPenyebut' => $tarif->PengaliDppPenyebut ?? 1,
                'DasarPengenaanPajak' => $rincian->dasarPengenaanPajak->KeString(),
                'JumlahPpn' => $rincian->jumlahPpn->KeString(),
                'Total' => $rincian->total->KeString(),
                'TerbitPada' => $sekarang,
                'JatuhTempoPada' => $this->TentukanJatuhTempo($jenis, $langganan, $sekarang),
                'IdPenggunaPembuat' => $idPengguna,
            ]);

            if ($kupon !== null && $rincian->bulanDiskon > 0) {
                KuponLanggananPemakaian::query()->create([
                    'IdKuponLangganan' => $kupon->Id,
                    'IdTenant' => $idTenant,
                    'IdTagihanLangganan' => $tagihan->Id,
                    'BulanDiskon' => $rincian->bulanDiskon,
                    'Diskon' => $rincian->diskon->KeString(),
                ]);
            }

            return $tagihan;
        });
    }

    /**
     * Paket Aktif, bukan harga negosiasi, bukan paket Gratis. Paket berjalan yang sudah diarsipkan tetap bisa
     * diperpanjang pemakainya (BR-P04.2).
     */
    private function TentukanPaket(Langganan $langganan, string $kodePaket): Paket
    {
        $paket = Paket::query()->where('Kode', strtoupper(trim($kodePaket)))->first();
        $paketBerjalan = $paket !== null && $paket->Id === $langganan->IdPaket && $this->CekBerjalan($langganan);
        $bolehDipilih = $paket !== null
            && ($paket->Status === StatusPaket::Aktif || ($paketBerjalan && $paket->Status === StatusPaket::Diarsipkan))
            && ! $paket->HargaNegosiasi
            && $paket->Kode !== (string) config('tenant.KodePaketGratis');

        if (! $bolehDipilih) {
            throw new PelanggaranAturanBisnis('PaketTidakTersedia', 'Paket ini tidak bisa dipilih. Pilih paket lain dari daftar.', 'KodePaket');
        }

        return $paket;
    }

    private function TentukanJenis(Langganan $langganan, Paket $paket): JenisTagihanLangganan
    {
        if ($this->CekBerjalan($langganan) && $paket->Id === $langganan->IdPaket) {
            return JenisTagihanLangganan::Perpanjangan;
        }

        if ($langganan->Status === StatusLangganan::Aktif) {
            throw new PelanggaranAturanBisnis(
                'GantiPaketBelumTersedia',
                'Ganti paket saat langganan masih aktif belum tersedia. Perpanjang paket Anda sekarang, atau hubungi tim kami untuk pindah paket.',
                'KodePaket',
            );
        }

        return JenisTagihanLangganan::Aktivasi;
    }

    private function CekBerjalan(Langganan $langganan): bool
    {
        return in_array($langganan->Status, [StatusLangganan::Aktif, StatusLangganan::Tertunggak], true);
    }

    private function TentukanJatuhTempo(JenisTagihanLangganan $jenis, Langganan $langganan, CarbonImmutable $sekarang): CarbonImmutable
    {
        $akhirPeriode = $langganan->PeriodeSelesai === null ? null : CarbonImmutable::instance($langganan->PeriodeSelesai);

        if ($jenis === JenisTagihanLangganan::Perpanjangan && $akhirPeriode !== null && $akhirPeriode->greaterThan($sekarang)) {
            return $akhirPeriode;
        }

        return $sekarang->addDays((int) config('tagihan.HariJatuhTempo'));
    }

    /** BR-P04.7: kupon aktif, belum lewat, berlaku untuk paket, kuota tenant berbeda belum habis. */
    private function CariKupon(int $idTenant, string $kode, Paket $paket): KuponLangganan
    {
        $kupon = KuponLangganan::query()->where('Kode', $kode)->lockForUpdate()->first();
        $hariIni = now('Asia/Jakarta')->toDateString();

        if ($kupon === null || ! $kupon->Aktif || ($kupon->BerlakuSampai !== null && $kupon->BerlakuSampai->toDateString() < $hariIni)) {
            throw new PelanggaranAturanBisnis('KuponTidakBerlaku', 'Kode kupon tidak berlaku.', 'KodeKupon');
        }

        if ($kupon->DaftarKodePaket !== null && ! in_array($paket->Kode, $kupon->DaftarKodePaket, true)) {
            throw new PelanggaranAturanBisnis('KuponBukanUntukPaket', "Kupon ini tidak berlaku untuk paket {$paket->Nama}.", 'KodeKupon');
        }

        $terpakai = $this->HitungBulanTerpakai($idTenant, $kupon);

        if ($terpakai >= $kupon->DurasiBulan) {
            throw new PelanggaranAturanBisnis('KuponSudahDipakai', 'Kupon ini sudah Anda pakai sampai habis.', 'KodeKupon');
        }

        if ($terpakai === 0 && $kupon->Kuota !== null) {
            $jumlahTenant = KuponLanggananPemakaian::query()
                ->where('IdKuponLangganan', $kupon->Id)
                ->whereNull('DibatalkanPada')
                ->distinct()
                ->count('IdTenant');

            if ($jumlahTenant >= $kupon->Kuota) {
                throw new PelanggaranAturanBisnis('KuotaKuponHabis', 'Kuota kupon ini sudah habis.', 'KodeKupon');
            }
        }

        return $kupon;
    }

    private function HitungBulanTerpakai(int $idTenant, KuponLangganan $kupon): int
    {
        return (int) KuponLanggananPemakaian::query()
            ->where('IdTenant', $idTenant)
            ->where('IdKuponLangganan', $kupon->Id)
            ->whereNull('DibatalkanPada')
            ->sum('BulanDiskon');
    }
}
