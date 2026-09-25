<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Layanan\PenilaiPemakaianAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;

/**
 * Bagan akun tenant untuk halaman `/kelola/akuntansi/akun` (F-13a, tipe FE `PropsBaganAkun`): urutan pohon (induk lalu
 * anak-anaknya, per tingkat urut kode) dengan kedalaman, penanda jurnal (tipe terkunci), peran yang dipetakan, dan
 * apakah akun kemungkinan bisa dihapus (pemeriksaan lengkap tetap di `HapusAkun`). Tiga kueri untuk seluruh bagan.
 */
final class DaftarBaganAkun
{
    public function __construct(private readonly PenilaiPemakaianAkun $pemakaian) {}

    /**
     * @return array{Akun: list<array<string, mixed>>, OpsiTipe: list<array{Nilai: string, Label: string, DigitAwal: string}>}
     */
    public function Ambil(): array
    {
        $semua = Akun::query()->orderBy('Kode')->get();
        $berjurnal = $this->pemakaian->AmbilIdAkunBerjurnal();
        $peran = [];

        foreach (PemetaanAkun::query()->get(['Kunci', 'IdAkun']) as $pemetaan) {
            $satu = PeranAkun::DariKunci($pemetaan->Kunci);

            if ($satu !== null) {
                $peran[$pemetaan->IdAkun][$satu->value] = $satu->AmbilLabel();
            }
        }

        $anak = [];
        $perId = [];

        foreach ($semua as $akun) {
            $perId[$akun->Id] = $akun;
        }

        foreach ($semua as $akun) {
            $induk = $akun->IdInduk !== null && isset($perId[$akun->IdInduk]) ? $akun->IdInduk : 0;
            $anak[$induk][] = $akun;
        }

        $hasil = [];
        $susun = function (int $idInduk, int $kedalaman) use (&$susun, &$hasil, $anak, $perId, $berjurnal, $peran): void {
            foreach ($anak[$idInduk] ?? [] as $akun) {
                $dipetakan = array_values($peran[$akun->Id] ?? []);
                $punyaAnak = isset($anak[$akun->Id]);
                $adaJurnal = isset($berjurnal[$akun->Id]);
                $induk = $akun->IdInduk !== null ? ($perId[$akun->IdInduk] ?? null) : null;

                $hasil[] = [
                    'Uuid' => $akun->Uuid,
                    'Kode' => $akun->Kode,
                    'Nama' => $akun->Nama,
                    'Jenis' => $akun->Jenis->value,
                    'LabelJenis' => $akun->Jenis->AmbilLabel(),
                    'SaldoNormal' => $akun->SaldoNormal->value,
                    'Kontra' => $akun->CekKontra(),
                    'KasBank' => $akun->KasBank,
                    'Aktif' => $akun->Aktif,
                    'Sistem' => $akun->Sistem,
                    'Kedalaman' => $kedalaman,
                    'UuidInduk' => $induk?->Uuid,
                    'KodeInduk' => $induk?->Kode,
                    'AdaJurnal' => $adaJurnal,
                    'PeranDipetakan' => $dipetakan,
                    'PunyaAnak' => $punyaAnak,
                    'BisaDihapus' => ! $adaJurnal && $dipetakan === [] && ! $punyaAnak,
                ];
                $susun($akun->Id, $kedalaman + 1);
            }
        };
        $susun(0, 0);

        return [
            'Akun' => $hasil,
            'OpsiTipe' => array_map(fn (TipeAkun $t): array => ['Nilai' => $t->value, 'Label' => $t->AmbilLabel(), 'DigitAwal' => $t->AmbilDigitAwalKode()], TipeAkun::cases()),
        ];
    }
}
