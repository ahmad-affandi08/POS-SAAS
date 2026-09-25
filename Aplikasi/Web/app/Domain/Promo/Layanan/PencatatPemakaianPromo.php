<?php

declare(strict_types=1);

namespace App\Domain\Promo\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\PromoPemakaian;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Promo untuk domain Penjualan (F-16c): mencatat pemakaian promo sebuah penjualan di transaksi
 * DB yang sama dan menambah `KuotaTerpakai` (baris promo dikunci urut Id). Idempoten per (promo, penjualan). Promo yang
 * tidak dikenal atau kuotanya terlampaui tidak menggagalkan penjualan; masalahnya dikembalikan untuk tinjauan.
 */
final class PencatatPemakaianPromo
{
    /**
     * @param  array<string, Uang>  $diskonPerPromo  kunci = Uuid promo
     * @return list<string> masalah untuk alasan tinjauan
     */
    public function Catat(int $idPenjualan, ?int $idPelanggan, CarbonImmutable $tanggalBisnis, array $diskonPerPromo): array
    {
        if ($diskonPerPromo === []) {
            return [];
        }

        $promo = Promo::query()->whereIn('Uuid', array_keys($diskonPerPromo))->orderBy('Id')->lockForUpdate()->get()->keyBy('Uuid');
        $masalah = [];

        foreach ($diskonPerPromo as $uuid => $diskon) {
            $p = $promo->get($uuid);

            if ($p === null) {
                $masalah[] = "promo {$uuid} tidak dikenal server";

                continue;
            }

            if (PromoPemakaian::query()->where('IdPromo', $p->Id)->where('IdPenjualan', $idPenjualan)->exists()) {
                continue;
            }

            PromoPemakaian::query()->create([
                'IdPromo' => $p->Id,
                'IdPenjualan' => $idPenjualan,
                'IdPelanggan' => $idPelanggan,
                'TanggalBisnis' => $tanggalBisnis->toDateString(),
                'JumlahDiskon' => $diskon->KeString(),
            ]);

            if ($p->Kuota !== null && $p->KuotaTerpakai >= $p->Kuota) {
                $masalah[] = "kuota promo {$p->Kode} ({$p->Kuota}) sudah habis";
            }

            $p->KuotaTerpakai++;
            $p->save();
        }

        return $masalah;
    }
}
