<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusStokOpname;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\StokOpname;
use App\Domain\Persediaan\Model\StokOpnameDetail;
use Brick\Math\BigDecimal;

/**
 * Detail & lembar hitung stok opname (tipe FE `PropsDetailStokOpname`). Hitung buta (F-05b): selama opname
 * Berlangsung, jumlah sistem, mutasi selama opname, dan selisih TIDAK dikirim ke peramban (null) sehingga penghitung
 * tidak bisa melihatnya lewat respons mana pun; setelah diajukan untuk ditinjau semuanya tampil. Selisih sementara
 * (sebelum disetujui) = fisik − (sistem + mutasi sejak snapshot) dihitung saat dibaca.
 */
final class DetailStokOpname
{
    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly InfoProdukStok $infoProduk,
        private readonly JurnalSumber $jurnalSumber,
        private readonly RiwayatDokumenPersediaan $riwayat,
    ) {}

    public static function CekSistemTersembunyi(StokOpname $o): bool
    {
        return $o->HitungButa && $o->Status === StatusStokOpname::Berlangsung;
    }

    /**
     * @return array{Opname: array<string, mixed>, Baris: list<array<string, mixed>>, Jurnal: list<array<string, mixed>>, Riwayat: list<array<string, mixed>>}
     */
    public function Ambil(StokOpname $o): array
    {
        $gudang = $this->infoGudang->AmbilBanyak([$o->IdGudang])[$o->IdGudang] ?? null;
        [$riwayat, $nama] = $this->riwayat->Ambil(StokOpname::JENIS_DOKUMEN, $o->Id, fn (string $s): string => StatusStokOpname::tryFrom($s)?->AmbilLabel() ?? $s, [$o->DibuatOleh, $o->DiajukanOleh, $o->DisetujuiOleh, $o->DibatalkanOleh]);
        $sembunyi = self::CekSistemTersembunyi($o);
        $sejak = $sembunyi || $o->Status !== StatusStokOpname::Ditinjau ? [] : $this->HitungMutasiSejak($o);

        return [
            'Opname' => [
                'Uuid' => $o->Uuid,
                'Nomor' => $o->Nomor,
                'Status' => $o->Status->value,
                'LabelStatus' => $o->Status->AmbilLabel(),
                'UuidGudang' => $gudang->uuid ?? '',
                'NamaGudang' => $gudang->nama ?? '',
                'NamaOutlet' => $gudang?->namaOutlet,
                'NamaKategori' => $o->NamaKategori,
                'HitungButa' => $o->HitungButa,
                'SistemTersembunyi' => $sembunyi,
                'TanggalSnapshot' => $o->TanggalSnapshot->format('Y-m-d'),
                'SnapshotPada' => $o->SnapshotPada->toIso8601String(),
                'TanggalPosting' => $o->TanggalPosting?->format('Y-m-d'),
                'Catatan' => $o->Catatan,
                'JumlahBaris' => $o->JumlahBaris,
                'JumlahDihitung' => $o->JumlahDihitung,
                'TotalNilaiLebih' => $o->TotalNilaiLebih,
                'TotalNilaiKurang' => $o->TotalNilaiKurang,
                'AlasanBatal' => $o->AlasanBatal,
                'DibuatOleh' => $nama($o->DibuatOleh),
                'DiajukanOleh' => $nama($o->DiajukanOleh),
                'DisetujuiOleh' => $nama($o->DisetujuiOleh),
                'DisetujuiPada' => $o->DisetujuiPada?->toIso8601String(),
            ],
            'Baris' => $this->AmbilBaris($o, $sembunyi, $sejak),
            'Jurnal' => $this->jurnalSumber->Ambil(JenisSumberJurnal::StokOpname, $o->Id),
            'Riwayat' => $riwayat,
        ];
    }

    /**
     * @param  array<int, string>  $sejak  IdDetail → Σ mutasi sejak snapshot (hanya saat Ditinjau)
     * @return list<array<string, mixed>>
     */
    private function AmbilBaris(StokOpname $o, bool $sembunyi, array $sejak): array
    {
        $detail = StokOpnameDetail::query()->where('IdStokOpname', $o->Id)->orderBy('Urutan')->get();
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($detail->pluck('IdProduk')->all())), true);

        return array_values($detail->map(function (StokOpnameDetail $d) use ($produk, $sembunyi, $sejak): array {
            $info = $produk[$d->IdProduk] ?? null;
            $mutasi = $d->MutasiSelamaOpname ?? ($sejak[$d->Id] ?? null);
            $selisih = $d->Selisih;

            if ($selisih === null && $mutasi !== null && $d->JumlahFisik !== null) {
                $selisih = Kuantitas::Dari($d->JumlahFisik)->Kurangi(Kuantitas::Dari($d->JumlahSistem)->Tambah(Kuantitas::Dari($mutasi)))->KeString();
            }

            return [
                'Urutan' => $d->Urutan,
                'UuidProduk' => $info->uuid ?? '',
                'NamaProduk' => $d->NamaProduk,
                'Sku' => $d->Sku,
                'SimbolSatuan' => $info->simbolSatuan ?? '',
                'BolehDesimal' => $info->bolehDesimal ?? false,
                'Pelacakan' => $info?->pelacakan->value ?? 'Tidak',
                'NomorBatch' => $d->NomorBatch,
                'TanggalKedaluwarsa' => $d->TanggalKedaluwarsa?->format('Y-m-d'),
                'NomorSeri' => $d->NomorSeri,
                'DariSnapshot' => $d->DariSnapshot,
                'JumlahSistem' => $sembunyi ? null : $d->JumlahSistem,
                'JumlahFisik' => $d->JumlahFisik,
                'MutasiSelamaOpname' => $sembunyi ? null : $mutasi,
                'Selisih' => $sembunyi ? null : $selisih,
                'NilaiSelisih' => $sembunyi ? null : $d->NilaiSelisih,
            ];
        })->all());
    }

    /**
     * @return array<int, string>
     */
    private function HitungMutasiSejak(StokOpname $o): array
    {
        $hasil = [];

        foreach (StokOpnameDetail::query()->where('IdStokOpname', $o->Id)->whereNotNull('JumlahFisik')->get() as $d) {
            $jumlah = MutasiStok::query()
                ->where('IdProduk', $d->IdProduk)
                ->where('IdGudang', $o->IdGudang)
                ->where('Id', '>', $d->IdMutasiSnapshot)
                ->when($d->NomorBatch !== null, fn ($k) => $k->where('IdBatchStok', $d->IdBatchStok ?? 0))
                ->when($d->NomorSeri !== null, fn ($k) => $k->where('IdNomorSeri', $d->IdNomorSeri ?? 0))
                ->sum('Jumlah');
            $hasil[$d->Id] = (string) BigDecimal::of((string) $jumlah)->toScale(4);
        }

        return $hasil;
    }
}
