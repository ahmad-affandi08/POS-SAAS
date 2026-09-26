<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pembelian\Kueri\DaftarPemasok;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;
use App\Domain\Promo\Model\PenerimaanKlaimPemasok;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-16c bagian 4b (J-16.5): pemasok membayar klaim promonya. Semua klaim `Terbuka` pemasok itu dengan tanggal bisnis ≤
 * tanggal penerimaan diterima sekaligus (lazim: pemasok membayar rekap klaim per periode). Jurnal Dr kas/bank, Cr HPP
 * sebesar total klaim (PSAK 72: imbalan dari pemasok mengurangi biaya pokok penjualan); potongan ke pelanggan tetap di
 * Diskon Penjualan jurnal penjualan. Periode terkunci ditolak oleh `PostingJurnal`. Audit `promo.klaim-pemasok.terima`.
 */
final class TerimaKlaimPemasok
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly DaftarPemasok $pemasok,
        private readonly DaftarAkunPilihan $akun,
        private readonly PostingJurnal $posting,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuidPemasok, CarbonImmutable $tanggal, string $uuidAkunKasBank, ?string $keterangan, int $idPengguna): PenerimaanKlaimPemasok
    {
        $idPemasok = $this->pemasok->AmbilIdDariUuid($uuidPemasok)
            ?? throw new PelanggaranAturanBisnis('PemasokTidakDitemukan', 'Pemasok tidak ditemukan.', 'Pemasok', 404);
        $hariIni = CarbonImmutable::now($this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'])->toDateString();

        if ($tanggal->toDateString() > $hariIni) {
            throw new PelanggaranAturanBisnis('TanggalDiMasaDepan', 'Tanggal tidak boleh setelah hari ini.', 'Tanggal');
        }

        $akun = $this->akun->CariKasBankDariUuid($uuidAkunKasBank)['Id']
            ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas atau bank.', 'AkunKasBank');

        return DB::transaction(function () use ($idPemasok, $tanggal, $akun, $keterangan, $idPengguna): PenerimaanKlaimPemasok {
            $klaim = KlaimPromoPemasok::query()
                ->where('IdPemasok', $idPemasok)
                ->where('Status', StatusKlaimPromo::Terbuka->value)
                ->where('TanggalBisnis', '<=', $tanggal->toDateString())
                ->orderBy('Id')
                ->lockForUpdate()
                ->get();
            $total = $klaim->reduce(fn (Uang $t, KlaimPromoPemasok $k): Uang => $t->Tambah(Uang::Dari((string) $k->Jumlah)), Uang::Nol());

            if ($total->Bandingkan(Uang::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('KlaimKosong', 'Tidak ada klaim terbuka untuk pemasok ini sampai tanggal itu.', 'Tanggal');
            }

            $nama = $this->pemasok->AmbilRingkas([$idPemasok])[$idPemasok]['Nama'] ?? '';
            $penerimaan = PenerimaanKlaimPemasok::query()->create([
                'IdPemasok' => $idPemasok,
                'Tanggal' => $tanggal->toDateString(),
                'Jumlah' => $total->KeString(),
                'IdAkunKasBank' => $akun,
                'Keterangan' => $keterangan,
                'DibuatOleh' => $idPengguna,
            ]);
            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::PenerimaanKlaimPemasok,
                idSumber: $penerimaan->Id,
                uuidSumber: $penerimaan->Uuid,
                nomorSumber: null,
                tanggal: $tanggal,
                keterangan: "Klaim promo {$nama} ({$klaim->count()} transaksi)",
                baris: [
                    new DataBarisJurnal(peran: null, idAkun: $akun, idOutlet: null, debit: $total, kredit: Uang::Nol()),
                    DataBarisJurnal::Kredit(PeranAkun::Hpp, $total, null),
                ],
                idPengguna: $idPengguna,
            ));
            $penerimaan->IdJurnal = $hasil->idJurnal;
            $penerimaan->save();
            KlaimPromoPemasok::query()->whereKey($klaim->pluck('Id')->all())->update([
                'Status' => StatusKlaimPromo::Diterima->value,
                'IdPenerimaanKlaimPemasok' => $penerimaan->Id,
                'DiubahPada' => now(),
            ]);
            $this->audit->Catat('promo.klaim-pemasok.terima', $penerimaan, nilaiBaru: [
                'Pemasok' => $nama,
                'Jumlah' => $total->KeString(),
                'JumlahKlaim' => $klaim->count(),
                'Tanggal' => $tanggal->toDateString(),
            ], idPengguna: $idPengguna);

            return $penerimaan;
        });
    }
}
