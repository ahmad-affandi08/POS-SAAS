<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Integrasi\Billing\GerbangBillingPlatform;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Tenant\Enum\JenisTagihanLangganan;
use App\Domain\Tenant\Enum\SiklusTagihan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Enum\TahapPengingatTagihan;
use App\Domain\Tenant\Kueri\HargaPaketBerlaku;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Model\KuponLangganan;
use App\Domain\Tenant\Model\KuponLanggananPemakaian;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\TagihanLangganan;
use Carbon\CarbonImmutable;

/**
 * Inti penerbitan tagihan langganan (P-08 langkah 1, BR-P08.5–P08.8), dipakai tagihan buatan Owner
 * (`BuatTagihanLangganan`) dan tagihan perpanjangan otomatis H-7 (`TerbitkanTagihanPerpanjanganOtomatis`) supaya
 * angka, nomor, kupon, dan jatuh temponya tidak bisa berbeda antara kedua jalur.
 *
 * Wajib dipanggil di dalam transaksi dengan baris `Langganan` sudah dikunci dan konteks tenant sudah diatur; pemanggil
 * memastikan tidak ada tagihan terbuka lain (BR-P08.4).
 */
final class PenerbitTagihanLangganan
{
    public function __construct(
        private readonly HargaPaketBerlaku $hargaBerlaku,
        private readonly TarifPajakBerlaku $tarifBerlaku,
        private readonly GerbangBillingPlatform $gerbang,
        private readonly KalkulatorTagihanLangganan $kalkulator,
        private readonly PenomorTagihanLangganan $penomor,
        private readonly TagihanLanggananTenant $tagihanTenant,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  int|null  $idPengguna  null = diterbitkan sistem (perpanjangan otomatis)
     */
    public function Terbitkan(
        Langganan $langganan,
        Paket $paket,
        JenisTagihanLangganan $jenis,
        SiklusTagihan $siklus,
        ?KuponLangganan $kupon,
        ?int $idPengguna,
    ): TagihanLangganan {
        $idTenant = $langganan->IdTenant;
        $sekarang = CarbonImmutable::now();

        $harga = $this->hargaBerlaku->Cari($paket->Id, $sekarang, $jenis === JenisTagihanLangganan::Perpanjangan ? $this->tagihanTenant->AmbilMulaiLanggananPaket($paket->Id) : null)
            ?? throw new PelanggaranAturanBisnis('HargaBelumTersedia', "Harga paket {$paket->Nama} belum tersedia. Hubungi tim kami.", 'KodePaket');
        $subtotal = $siklus === SiklusTagihan::Tahunan ? $harga->AmbilHargaTahunan() : $harga->AmbilHargaBulanan();

        if ($subtotal->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('PaketTanpaBiaya', "Paket {$paket->Nama} tidak memerlukan tagihan.", 'KodePaket');
        }

        if (! $this->gerbang->CekAktif()) {
            throw new PelanggaranAturanBisnis('GerbangBelumAktif', 'Pembayaran tagihan belum dibuka karena gerbang pembayaran online belum diaktifkan. Hubungi tim kami.');
        }

        $tarif = null;

        if ((bool) config('tagihan.PlatformPkp')) {
            $tarif = $this->tarifBerlaku->Cari('Ppn', null, $sekarang)
                ?? throw new PelanggaranAturanBisnis('TarifPpnBelumTerbit', 'Tagihan belum bisa dibuat karena tarif PPN belum diterbitkan. Hubungi tim kami.');
        }

        $jumlahBulan = PenghitungPeriodeLangganan::JumlahBulan($siklus);
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

        $jatuhTempo = $this->TentukanJatuhTempo($jenis, $langganan, $sekarang);
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
            'JatuhTempoPada' => $jatuhTempo,
            'IdPenggunaPembuat' => $idPengguna,
            // Owner yang membuat tagihannya sendiri sudah tahu tagihan itu terbit: tahap pengingat yang sedang
            // berjalan dianggap terkirim. Tagihan dari sistem dibiarkan kosong supaya pengingat pertama dikirim.
            'PengingatTerakhir' => $idPengguna === null ? null : TahapPengingatTagihan::Tentukan($sekarang, $jatuhTempo)?->value,
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

        $this->audit->Catat($idPengguna === null ? 'langganan.tagihan-otomatis' : 'langganan.tagihan-buat', $tagihan, nilaiBaru: [
            'Nomor' => $tagihan->Nomor, 'Jenis' => $jenis->value, 'Paket' => $paket->Kode, 'Siklus' => $siklus->value,
            'Total' => $rincian->total->KeString(), 'KodeKupon' => $kupon?->Kode,
        ], idTenant: $idTenant);

        return $tagihan;
    }

    /** Bulan berdiskon kupon yang sudah dipakai tenant ini (pemakaian yang dibatalkan tidak dihitung, BR-P08.7). */
    public function HitungBulanTerpakai(int $idTenant, KuponLangganan $kupon): int
    {
        return (int) KuponLanggananPemakaian::query()
            ->where('IdTenant', $idTenant)
            ->where('IdKuponLangganan', $kupon->Id)
            ->whereNull('DibatalkanPada')
            ->sum('BulanDiskon');
    }

    /** BR-P08.8: perpanjangan jatuh tempo di akhir periode berjalan; selain itu terbit + `tagihan.HariJatuhTempo`. */
    private function TentukanJatuhTempo(JenisTagihanLangganan $jenis, Langganan $langganan, CarbonImmutable $sekarang): CarbonImmutable
    {
        $akhirPeriode = $langganan->PeriodeSelesai === null ? null : CarbonImmutable::instance($langganan->PeriodeSelesai);

        if ($jenis === JenisTagihanLangganan::Perpanjangan && $akhirPeriode !== null && $akhirPeriode->greaterThan($sekarang)) {
            return $akhirPeriode;
        }

        return $sekarang->addDays((int) config('tagihan.HariJatuhTempo'));
    }
}
