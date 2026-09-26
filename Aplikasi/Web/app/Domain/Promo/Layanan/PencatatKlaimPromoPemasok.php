<?php

declare(strict_types=1);

namespace App\Domain\Promo\Layanan;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenyediaAkunPeran;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;
use App\Domain\Promo\Model\Promo;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Promo untuk domain Penjualan (F-16c bagian 4b & 4d, pendanaan promo): mencatat klaim ke
 * pemasok untuk promo yang ditanggung pemasok, di transaksi DB penerimaan penjualan (idempoten per promo & penjualan),
 * dan membatalkan klaim yang belum diterima saat penjualan di-void.
 *
 * Basis akrual (v1.93, SAK EMKM/PSAK 72): klaim diakui saat penjualan terjadi, Dr Piutang Klaim Promosi Pemasok, Cr HPP
 * (imbalan dari pemasok mengurangi biaya pokok, bukan pendapatan), jurnal bersumber penjualan itu (kunci
 * `KlaimPemasok-{IdPromo}`) di tanggal bisnisnya. Void membalik jurnal itu. Akun piutang klaim disediakan otomatis
 * untuk tenant lama yang belum memetakannya (`PenyediaAkunPeran`).
 */
final class PencatatKlaimPromoPemasok
{
    public const KODE_AKUN = '1-1460';

    public const NAMA_AKUN = 'Piutang Klaim Promosi Pemasok';

    public function __construct(
        private readonly PostingJurnal $posting,
        private readonly BalikkanJurnal $balikkan,
        private readonly PenyediaAkunPeran $penyediaAkun,
    ) {}

    /**
     * @param  array<string, Uang>  $diskonPerPromo  kunci = Uuid promo, nilai = total potongan promo di penjualan ini
     */
    public function Catat(int $idPenjualan, string $uuidPenjualan, string $nomorPenjualan, int $idOutlet, CarbonImmutable $tanggalBisnis, array $diskonPerPromo, ?int $idPengguna): void
    {
        if ($diskonPerPromo === []) {
            return;
        }

        $promo = Promo::query()->whereIn('Uuid', array_keys($diskonPerPromo))->whereNotNull('IdPemasok')->get();

        foreach ($promo as $p) {
            $persen = BigDecimal::of((string) $p->PersenDanaPemasok);
            $diskon = $diskonPerPromo[$p->Uuid];

            if ($p->IdPemasok === null || ! $persen->isPositive() || $diskon->Bandingkan(Uang::Nol()) <= 0) {
                continue;
            }

            if (KlaimPromoPemasok::query()->where('IdPromo', $p->Id)->where('IdPenjualan', $idPenjualan)->exists()) {
                continue;
            }

            $jumlah = $diskon->Kali($persen->withPointMovedLeft(2));
            $klaim = KlaimPromoPemasok::query()->create([
                'IdPromo' => $p->Id,
                'IdPemasok' => $p->IdPemasok,
                'IdPenjualan' => $idPenjualan,
                'IdOutlet' => $idOutlet,
                'TanggalBisnis' => $tanggalBisnis->toDateString(),
                'JumlahDiskon' => $diskon->KeString(),
                'PersenDana' => (string) $persen->toScale(2),
                'Jumlah' => $jumlah->KeString(),
                'Status' => StatusKlaimPromo::Terbuka,
            ]);

            if ($jumlah->Bandingkan(Uang::Nol()) <= 0) {
                continue;
            }

            $idAkun = $this->penyediaAkun->Pastikan(PeranAkun::PiutangKlaimPemasok, self::KODE_AKUN, self::NAMA_AKUN, $idOutlet);
            $klaim->IdJurnal = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::Penjualan,
                idSumber: $idPenjualan,
                uuidSumber: $uuidPenjualan,
                nomorSumber: $nomorPenjualan,
                tanggal: $tanggalBisnis,
                keterangan: mb_substr("Klaim promo {$p->Kode} ke pemasok ({$nomorPenjualan})", 0, 255),
                baris: [
                    new DataBarisJurnal(peran: null, idAkun: $idAkun, idOutlet: $idOutlet, debit: $jumlah, kredit: Uang::Nol()),
                    DataBarisJurnal::Kredit(PeranAkun::Hpp, $jumlah, $idOutlet),
                ],
                idPengguna: $idPengguna,
                kunciSumber: 'KlaimPemasok-'.$p->Id,
            ))->idJurnal;
            $klaim->save();
        }
    }

    /**
     * Void penjualan: klaim yang belum diterima dibatalkan dan jurnal akrualnya dibalik di tanggal bisnis void. Klaim
     * yang sudah diterima/dipotong dari hutang tetap (koreksi dengan pemasok dilakukan manual).
     */
    public function Batalkan(int $idPenjualan, ?CarbonImmutable $tanggalBisnis = null, ?int $idPengguna = null): void
    {
        $klaim = KlaimPromoPemasok::query()
            ->where('IdPenjualan', $idPenjualan)
            ->where('Status', StatusKlaimPromo::Terbuka->value)
            ->lockForUpdate()
            ->get();

        foreach ($klaim as $k) {
            if ($k->IdJurnal !== null && $k->IdJurnalBatal === null) {
                $k->IdJurnalBatal = $this->balikkan->Jalankan(
                    $k->IdJurnal,
                    $tanggalBisnis ?? CarbonImmutable::now(),
                    'Batal klaim promo pemasok (void penjualan)',
                    JenisSumberJurnal::Penjualan,
                    $idPenjualan,
                    'KlaimPemasokBatal-'.$k->IdPromo,
                    $idPengguna,
                )->idJurnal;
            }

            $k->Status = StatusKlaimPromo::Dibatalkan;
            $k->save();
        }
    }
}
