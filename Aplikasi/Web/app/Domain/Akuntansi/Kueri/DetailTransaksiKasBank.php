<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;

/**
 * Detail satu transaksi kas & bank (F-13a, tipe FE `PropsDetailTransaksiKasBank`) beserta jurnalnya, dokumen
 * pembalik/yang dibalik, dan info lampiran (tanpa path). Null bila tidak ada di tenant aktif atau di luar outlet akses.
 */
final class DetailTransaksiKasBank
{
    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly DaftarAnggota $anggota,
        private readonly JurnalSumber $jurnal,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function Cari(string $uuid, ?array $idOutletBoleh): ?TransaksiKasBank
    {
        $kueri = TransaksiKasBank::query()->where('Uuid', $uuid);
        DaftarTransaksiKasBank::BatasiOutlet($kueri, $idOutletBoleh);

        return $kueri->first();
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>|null
     */
    public function Ambil(string $uuid, ?array $idOutletBoleh): ?array
    {
        $t = $this->Cari($uuid, $idOutletBoleh);

        if (! $t instanceof TransaksiKasBank) {
            return null;
        }

        $akun = Akun::query()->whereKey([$t->IdAkunSumber, $t->IdAkunTujuan])->get(['Id', 'Kode', 'Nama'])->keyBy('Id');
        $namaOutlet = $t->IdOutlet === null ? null : (array_column($this->outlet->AmbilRingkas([$t->IdOutlet]), 'Nama', 'Id')[$t->IdOutlet] ?? null);
        $pembuat = $t->DibuatOleh === null ? [] : $this->anggota->AmbilNamaPengguna($t->IdTenant, [$t->DibuatOleh]);
        $dibalik = $t->IdTransaksiDibalik === null ? null : TransaksiKasBank::query()->whereKey($t->IdTransaksiDibalik)->first(['Uuid', 'Nomor']);
        $pembalik = TransaksiKasBank::query()->where('IdTransaksiDibalik', $t->Id)->first(['Uuid', 'Nomor']);

        return [
            'Transaksi' => [
                ...DaftarTransaksiKasBank::PetakanBaris($t, $akun->get($t->IdAkunSumber), $akun->get($t->IdAkunTujuan), $namaOutlet),
                'Dibalik' => $pembalik instanceof TransaksiKasBank,
                'DibuatOleh' => $t->DibuatOleh === null ? null : ($pembuat[$t->DibuatOleh] ?? null),
                'DibuatPada' => $t->DibuatPada?->toIso8601String() ?? '',
                'UuidDibalik' => $dibalik?->Uuid,
                'NomorDibalik' => $dibalik?->Nomor,
                'UuidPembalik' => $pembalik?->Uuid,
                'NomorPembalik' => $pembalik?->Nomor,
                'Lampiran' => $t->PathLampiran === null ? null : ['Nama' => $t->NamaLampiran ?? 'Lampiran', 'Ukuran' => $t->UkuranLampiran ?? 0],
            ],
            'Jurnal' => $this->jurnal->Ambil(JenisSumberJurnal::TransaksiKasBank, $t->Id),
        ];
    }
}
