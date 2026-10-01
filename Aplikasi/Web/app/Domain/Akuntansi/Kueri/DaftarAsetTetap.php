<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\KelompokAsetTetap;
use App\Domain\Akuntansi\Enum\StatusAsetTetap;
use App\Domain\Akuntansi\Layanan\PenghitungPenyusutan;
use App\Domain\Akuntansi\Model\AsetTetap;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\PenyusutanAset;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Illuminate\Support\Collection;

/**
 * Daftar & rincian aset tetap (FIN-10). Cari: nomor & nama. Saring: `Status`, `Kelompok`. Akumulasi = akumulasi awal
 * + Σ penyusutan yang sudah dijurnal; nilai buku = harga perolehan − akumulasi (0 untuk aset yang dilepas/dibatalkan
 * tetap ditampilkan apa adanya sebagai riwayat).
 */
final class DaftarAsetTetap
{
    public const KOLOM_URUT = ['Nomor', 'Nama', 'TanggalPerolehan', 'HargaPerolehan'];

    public const KOLOM_SARING = ['Status', 'Kelompok'];

    public const URUT_BAWAAN = '-TanggalPerolehan';

    public function __construct(private readonly PetaUuidOutlet $petaOutlet) {}

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p): array
    {
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusAsetTetap $s): string => $s->value, StatusAsetTetap::cases()));
        $kelompok = $p->AmbilDaftar('Kelompok', array_map(fn (KelompokAsetTetap $k): string => $k->value, KelompokAsetTetap::cases()));
        $pola = PenerapKueriTabel::PolaCari($p->cari);

        $kueri = AsetTetap::query()
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($kelompok !== [], fn ($k) => $k->whereIn('Kelompok', $kelompok))
            ->when($p->cari !== '', fn ($k) => $k->where(fn ($d) => $d->where('Nomor', 'like', $pola)->orWhere('Nama', 'like', $pola)));

        return PenerapKueriTabel::Terapkan($kueri, $p, array_combine(self::KOLOM_URUT, self::KOLOM_URUT), function (Collection $baris): array {
            /** @var Collection<int, AsetTetap> $baris */
            $akumulasi = $this->AmbilDisusutkan(array_values($baris->map(fn (AsetTetap $a): int => $a->Id)->all()));
            $outlet = $this->AmbilNamaOutlet(array_values(array_filter($baris->map(fn (AsetTetap $a): ?int => $a->IdOutlet)->all(), 'is_int')));

            return array_values($baris->map(fn (AsetTetap $a): array => self::Petakan($a, $akumulasi[$a->Id] ?? Uang::Nol(), $outlet[$a->IdOutlet ?? 0] ?? null))->all());
        });
    }

    /**
     * Rincian satu aset: data aset, jadwal penyusutan lengkap (dijurnal atau belum), dan jurnal terkait.
     *
     * @return array<string, mixed>
     */
    public function AmbilDetail(AsetTetap $aset): array
    {
        $tercatat = PenyusutanAset::query()->where('IdAsetTetap', $aset->Id)->get()->keyBy('Periode');
        $jurnal = Jurnal::query()->whereKey(array_values(array_filter([$aset->IdJurnal, $aset->IdJurnalPelepasan, ...$tercatat->pluck('IdJurnal')->all()], 'is_int')))->get(['Id', 'Uuid', 'Nomor'])->keyBy('Id');
        $tautan = function (?int $id) use ($jurnal): ?array {
            $satu = $id === null ? null : $jurnal->get($id);

            return $satu === null ? null : ['Uuid' => $satu->Uuid, 'Nomor' => $satu->Nomor];
        };
        $jadwal = PenghitungPenyusutan::HitungJadwal(Uang::Dari($aset->HargaPerolehan), Uang::Dari($aset->NilaiSisa), Uang::Dari($aset->AkumulasiAwal), $aset->UmurBulan, $aset->TanggalPerolehan->format('Y-m'), $aset->PeriodeMulai);
        $baris = [];
        $berjalan = Uang::Dari($aset->AkumulasiAwal);

        foreach ($jadwal as $bulan) {
            $catat = $tercatat->get($bulan['Periode']);
            $jumlah = $catat === null ? $bulan['Jumlah'] : Uang::Dari($catat->Jumlah);
            $berjalan = $berjalan->Tambah($jumlah);
            $baris[] = [
                'Periode' => $bulan['Periode'],
                'Jumlah' => $jumlah->KeString(),
                'Akumulasi' => $berjalan->KeString(),
                'NilaiBuku' => Uang::Dari($aset->HargaPerolehan)->Kurangi($berjalan)->KeString(),
                'Dijurnal' => $catat !== null,
                'Jurnal' => $catat === null ? null : $tautan($catat->IdJurnal),
            ];
        }

        $disusutkan = $this->AmbilDisusutkan([$aset->Id])[$aset->Id] ?? Uang::Nol();
        $outlet = $aset->IdOutlet === null ? null : ($this->AmbilNamaOutlet([$aset->IdOutlet])[$aset->IdOutlet] ?? null);

        return [
            'Aset' => self::Petakan($aset, $disusutkan, $outlet) + [
                'NilaiSisa' => (string) $aset->NilaiSisa,
                'AkumulasiAwal' => (string) $aset->AkumulasiAwal,
                'PeriodeMulai' => $aset->PeriodeMulai,
                'SumberDana' => $aset->SumberDana->value,
                'Catatan' => $aset->Catatan,
                'TanggalPelepasan' => $aset->TanggalPelepasan?->toDateString(),
                'NilaiPelepasan' => $aset->NilaiPelepasan === null ? null : (string) $aset->NilaiPelepasan,
                'AlasanBatal' => $aset->AlasanBatal,
                'JurnalPerolehan' => $tautan($aset->IdJurnal),
                'JurnalPelepasan' => $tautan($aset->IdJurnalPelepasan),
                'BisaDibatalkan' => $aset->Status === StatusAsetTetap::Aktif && $tercatat->isEmpty(),
            ],
            'Jadwal' => $baris,
        ];
    }

    /**
     * @param  list<int>  $idOutlet
     * @return array<int, string>
     */
    private function AmbilNamaOutlet(array $idOutlet): array
    {
        $hasil = [];

        foreach ($this->petaOutlet->AmbilIdentitas($idOutlet) as $id => $identitas) {
            $hasil[$id] = $identitas['Nama'];
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $id
     * @return array<int, Uang> Σ penyusutan dijurnal per aset
     */
    private function AmbilDisusutkan(array $id): array
    {
        if ($id === []) {
            return [];
        }

        $hasil = [];

        foreach (PenyusutanAset::query()->whereIn('IdAsetTetap', $id)->groupBy('IdAsetTetap')->selectRaw('IdAsetTetap, SUM(Jumlah) AS Total')->get() as $b) {
            $hasil[(int) $b->getAttribute('IdAsetTetap')] = Uang::Dari((string) $b->getAttribute('Total'));
        }

        return $hasil;
    }

    /**
     * @return array<string, mixed>
     */
    private static function Petakan(AsetTetap $a, Uang $disusutkan, ?string $namaOutlet): array
    {
        $akumulasi = Uang::Dari($a->AkumulasiAwal)->Tambah($disusutkan);

        return [
            'Uuid' => $a->Uuid,
            'Nomor' => $a->Nomor,
            'Nama' => $a->Nama,
            'Kelompok' => $a->Kelompok->value,
            'LabelKelompok' => $a->Kelompok->AmbilLabelSingkat(),
            'NamaOutlet' => $namaOutlet,
            'TanggalPerolehan' => $a->TanggalPerolehan->toDateString(),
            'HargaPerolehan' => (string) $a->HargaPerolehan,
            'UmurBulan' => $a->UmurBulan,
            'Akumulasi' => $akumulasi->KeString(),
            'NilaiBuku' => Uang::Dari($a->HargaPerolehan)->Kurangi($akumulasi)->KeString(),
            'Status' => $a->Status->value,
            'LabelStatus' => $a->Status->AmbilLabel(),
        ];
    }
}
