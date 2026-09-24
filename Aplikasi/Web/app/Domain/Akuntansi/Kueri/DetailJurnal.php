<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;

/**
 * Detail satu jurnal (tipe FE `PropsDetailJurnal`, DesainF05a C.5/E); null bila tidak ada di tenant aktif atau
 * memuat baris di luar outlet akses pengguna. Nama outlet & pembuat lewat kueri publik Organisasi; tautan sumber
 * dari `JenisSumberJurnal::BuatTautan` (tanpa kueri lintas domain).
 */
final class DetailJurnal
{
    public function __construct(
        private readonly PetaUuidOutlet $petaOutlet,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return array<string, mixed>|null
     */
    public function Ambil(string $uuid, ?array $idOutletBoleh = null): ?array
    {
        $kueri = Jurnal::query()->where('Uuid', $uuid);
        DaftarJurnal::BatasiOutlet($kueri, $idOutletBoleh);
        $jurnal = $kueri->first();

        if (! $jurnal instanceof Jurnal) {
            return null;
        }

        $detail = JurnalDetail::query()->where('IdJurnal', $jurnal->Id)->orderBy('Urutan')->get();
        $akun = Akun::query()->whereKey($detail->pluck('IdAkun')->unique()->values()->all())->get(['Id', 'Kode', 'Nama'])->keyBy('Id');
        $idOutlet = array_values(array_unique(array_filter($detail->pluck('IdOutlet')->all(), 'is_int')));
        $namaOutlet = array_column($idOutlet === [] ? [] : $this->petaOutlet->AmbilRingkas($idOutlet), 'Nama', 'Id');
        $namaPembuat = $jurnal->DibuatOleh === null ? [] : $this->anggota->AmbilNamaPengguna($jurnal->IdTenant, [$jurnal->DibuatOleh]);

        $dibalik = $jurnal->IdJurnalDibalik === null ? null : Jurnal::query()->whereKey($jurnal->IdJurnalDibalik)->first(['Uuid', 'Nomor']);
        $pembalik = Jurnal::query()->where('IdJurnalDibalik', $jurnal->Id)->first(['Uuid', 'Nomor']);

        return [
            'Jurnal' => [
                ...DaftarJurnal::PetakanBaris($jurnal, $pembalik instanceof Jurnal),
                'Periode' => $jurnal->Periode,
                'DibuatOleh' => $jurnal->DibuatOleh === null ? null : ($namaPembuat[$jurnal->DibuatOleh] ?? null),
                'DibuatPada' => $jurnal->DibuatPada?->toIso8601String() ?? '',
                'UuidJurnalDibalik' => $dibalik?->Uuid,
                'NomorJurnalDibalik' => $dibalik?->Nomor,
                'UuidPembalik' => $pembalik?->Uuid,
                'NomorPembalik' => $pembalik?->Nomor,
            ],
            'Baris' => $detail->map(function (JurnalDetail $d) use ($akun, $namaOutlet): array {
                $satuAkun = $akun->get($d->IdAkun);

                return [
                    'Urutan' => $d->Urutan,
                    'KodeAkun' => $satuAkun instanceof Akun ? $satuAkun->Kode : '',
                    'NamaAkun' => $satuAkun instanceof Akun ? $satuAkun->Nama : '',
                    'NamaOutlet' => $d->IdOutlet === null ? null : ($namaOutlet[$d->IdOutlet] ?? null),
                    'Debit' => $d->Debit,
                    'Kredit' => $d->Kredit,
                    'Memo' => $d->Memo,
                ];
            })->values()->all(),
            'Total' => ['Debit' => $jurnal->TotalDebit, 'Kredit' => $jurnal->TotalKredit],
        ];
    }
}
