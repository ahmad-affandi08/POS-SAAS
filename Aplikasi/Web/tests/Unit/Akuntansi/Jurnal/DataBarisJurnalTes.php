<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use Carbon\CarbonImmutable;

describe('F-05a DataBarisJurnal & validasi baris tanpa database (DesainF05a C.5)', function (): void {
    it('Debit/Kredit mengisi tepat satu sisi dengan peran', function (): void {
        $debit = DataBarisJurnal::Debit(PeranAkun::PersediaanBarangDagang, Uang::Dari('12345.68'), 3, 'Minyak goreng');
        $kredit = DataBarisJurnal::Kredit(PeranAkun::EkuitasSaldoAwal, Uang::Dari('12345.68'));

        expect($debit->peran)->toBe(PeranAkun::PersediaanBarangDagang)
            ->and($debit->idAkun)->toBeNull()
            ->and($debit->idOutlet)->toBe(3)
            ->and($debit->debit->KeString())->toBe('12345.68')
            ->and($debit->kredit->BernilaiNol())->toBeTrue()
            ->and($debit->memo)->toBe('Minyak goreng')
            ->and($kredit->debit->BernilaiNol())->toBeTrue()
            ->and($kredit->kredit->KeString())->toBe('12345.68');
    });

    it('BR-04.3: DariSelisih positif = debit, negatif = kredit sebesar besarannya, nol = tanpa baris', function (): void {
        $positif = DataBarisJurnal::DariSelisih(PeranAkun::SelisihHpp, Uang::Dari('400.00'), 1);
        $negatif = DataBarisJurnal::DariSelisih(PeranAkun::SelisihHpp, Uang::Dari('-400.00'), 1);

        expect($positif?->debit->KeString())->toBe('400.00')
            ->and($positif?->kredit->BernilaiNol())->toBeTrue()
            ->and($negatif?->kredit->KeString())->toBe('400.00')
            ->and($negatif?->debit->BernilaiNol())->toBeTrue()
            ->and($negatif?->idOutlet)->toBe(1)
            ->and(DataBarisJurnal::DariSelisih(PeranAkun::SelisihHpp, Uang::Nol()))->toBeNull();
    });

    it('J-05.1: baris negatif atau dua sisi ditolak BarisJurnalTidakValid sebelum menyentuh database', function (DataBarisJurnal $baris, string $pesan): void {
        app(KonteksTenant::class)->Atur(1);
        $data = new DataJurnal(
            jenisSumber: JenisSumberJurnal::StokAwal,
            idSumber: 1,
            uuidSumber: null,
            nomorSumber: null,
            tanggal: CarbonImmutable::parse('2026-09-24'),
            keterangan: 'Stok awal',
            baris: [$baris],
            idPengguna: null,
        );

        expect(fn () => app(PostingJurnal::class)->Jalankan($data))->toThrow(PelanggaranAturanBisnis::class, $pesan);
    })->with([
        'kredit negatif' => [new DataBarisJurnal(PeranAkun::EkuitasSaldoAwal, null, null, Uang::Nol(), Uang::Dari('-0.01')), 'Baris jurnal ke-1 tidak boleh bernilai negatif.'],
        'dua sisi' => [new DataBarisJurnal(PeranAkun::EkuitasSaldoAwal, null, null, Uang::Dari('0.01'), Uang::Dari('0.01')), 'Baris jurnal ke-1 hanya boleh berisi debit atau kredit, tidak keduanya.'],
        'tanpa peran & akun' => [new DataBarisJurnal(null, null, null, Uang::Dari('0.01'), Uang::Nol()), 'Baris jurnal ke-1 harus menyebut tepat satu: peran akun atau akun.'],
    ]);

    it('PenjagaKunciPeriode: nama periode Indonesia dan format periode wajib YYYY-MM', function (): void {
        expect(PenjagaKunciPeriode::FormatPeriode('2026-09'))->toBe('September 2026')
            ->and(PenjagaKunciPeriode::FormatPeriode('2027-01'))->toBe('Januari 2027')
            ->and(PenjagaKunciPeriode::FormatPeriode('2026-12'))->toBe('Desember 2026')
            ->and(fn () => app(PenjagaKunciPeriode::class)->CekTerkunci('2026-13'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => app(PenjagaKunciPeriode::class)->CekTerkunci('09-2026'))->toThrow(InvalidArgumentException::class);
    });
});
