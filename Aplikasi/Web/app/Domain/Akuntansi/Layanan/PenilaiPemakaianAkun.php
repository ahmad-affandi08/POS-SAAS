<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kontrak\PemeriksaPemakaianAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use Illuminate\Contracts\Container\Container;

/**
 * F-13a: menilai apakah akun sudah dipakai. Akun yang sudah punya jurnal, dipetakan ke peran akun, punya akun anak,
 * dirujuk transaksi kas & bank, atau dipakai domain lain (`PemeriksaPemakaianAkun::TAG`: kategori kas, metode
 * pembayaran) tidak bisa dihapus, hanya dinonaktifkan. Tipe akun terkunci begitu akun punya jurnal.
 */
final class PenilaiPemakaianAkun
{
    public function __construct(private readonly Container $kontainer) {}

    public function CekAdaJurnal(int $idAkun): bool
    {
        return JurnalDetail::query()->where('IdAkun', $idAkun)->exists();
    }

    /**
     * Id akun yang sudah punya baris jurnal (satu kueri untuk seluruh bagan akun, lewat indeks IdTenant+IdAkun).
     *
     * @return array<int, true>
     */
    public function AmbilIdAkunBerjurnal(): array
    {
        $hasil = [];

        foreach (JurnalDetail::query()->distinct()->pluck('IdAkun') as $id) {
            $hasil[(int) $id] = true;
        }

        return $hasil;
    }

    /**
     * Peran yang dipetakan ke akun ini (tingkat tenant & outlet), tanpa duplikat.
     *
     * @return list<PeranAkun>
     */
    public function AmbilPeranDipetakan(int $idAkun): array
    {
        $hasil = [];

        foreach (PemetaanAkun::query()->where('IdAkun', $idAkun)->pluck('Kunci') as $kunci) {
            $peran = PeranAkun::DariKunci((string) $kunci);

            if ($peran !== null) {
                $hasil[$peran->value] = $peran;
            }
        }

        return array_values($hasil);
    }

    public function AmbilAlasanDipakai(Akun $akun): ?string
    {
        if ($this->CekAdaJurnal($akun->Id)) {
            return 'sudah punya jurnal';
        }

        $peran = $this->AmbilPeranDipetakan($akun->Id);

        if ($peran !== []) {
            return 'dipetakan untuk '.implode(', ', array_map(fn (PeranAkun $p): string => $p->AmbilLabel(), $peran));
        }

        if (Akun::query()->where('IdInduk', $akun->Id)->exists()) {
            return 'punya akun anak';
        }

        if (TransaksiKasBank::query()->where(fn ($k) => $k->where('IdAkunSumber', $akun->Id)->orWhere('IdAkunTujuan', $akun->Id))->exists()) {
            return 'dipakai transaksi kas & bank';
        }

        foreach ($this->kontainer->tagged(PemeriksaPemakaianAkun::TAG) as $pemeriksa) {
            if (! $pemeriksa instanceof PemeriksaPemakaianAkun) {
                continue;
            }

            $alasan = $pemeriksa->PeriksaPemakaian($akun->Id);

            if ($alasan !== null) {
                return $alasan;
            }
        }

        return null;
    }
}
