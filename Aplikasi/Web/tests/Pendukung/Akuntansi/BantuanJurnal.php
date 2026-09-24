<?php

declare(strict_types=1);

namespace Tests\Pendukung\Akuntansi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Data\HasilPostingJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Bantuan test inti jurnal F-05a Tim B (DesainF05a C.5). Tenant & COA dari `BantuanPersediaan::SiapkanTenant()`.
 */
final class BantuanJurnal
{
    /**
     * Jurnal stok awal J-05.1 sederhana: Dr Persediaan barang dagang, Cr Ekuitas saldo awal sebesar `nilai`.
     *
     * @param  list<DataBarisJurnal>|null  $baris
     */
    public static function DataStokAwal(
        int $idSumber = 1,
        string $nilai = '12345678.90',
        string $tanggal = '2026-09-24',
        ?int $idOutlet = null,
        ?array $baris = null,
        string $kunciSumber = 'Utama',
        bool $otomatis = true,
        ?int $idPengguna = null,
    ): DataJurnal {
        return new DataJurnal(
            jenisSumber: JenisSumberJurnal::StokAwal,
            idSumber: $idSumber,
            uuidSumber: (string) Str::ulid(),
            nomorSumber: sprintf('SA/2026/09/%04d', $idSumber),
            tanggal: CarbonImmutable::parse($tanggal),
            keterangan: 'Stok awal Gudang Toko Outlet Utama',
            baris: $baris ?? [
                DataBarisJurnal::Debit(PeranAkun::PersediaanBarangDagang, Uang::Dari($nilai), $idOutlet),
                DataBarisJurnal::Kredit(PeranAkun::EkuitasSaldoAwal, Uang::Dari($nilai), $idOutlet),
            ],
            idPengguna: $idPengguna,
            kunciSumber: $kunciSumber,
            otomatis: $otomatis,
        );
    }

    public static function Posting(DataJurnal $data): HasilPostingJurnal
    {
        return app(PostingJurnal::class)->Jalankan($data);
    }

    public static function IdAkunPeran(PeranAkun $peran): int
    {
        return (int) PemetaanAkun::query()->where('Kunci', $peran->value)->whereNull('IdOutlet')->value('IdAkun');
    }

    /** Akun tenant bertipe `tipe` yang bukan akun `kecuali`. */
    public static function AkunLain(TipeAkun $tipe, int $kecuali): Akun
    {
        return Akun::query()->where('Jenis', $tipe->value)->whereKeyNot($kecuali)->orderBy('Kode')->firstOrFail();
    }

    public static function BuatOutlet(string $kode = 'SOLO', string $nama = 'Cabang Solo Baru'): Outlet
    {
        return Outlet::query()->create(['IdMerek' => Merek::query()->value('Id'), 'Kode' => $kode, 'Nama' => $nama]);
    }
}
