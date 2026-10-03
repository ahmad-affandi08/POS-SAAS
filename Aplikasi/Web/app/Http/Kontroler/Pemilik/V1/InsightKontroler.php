<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Laporan\Kueri\InsightMingguan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pemilik/v1/insight` (OWN-11, izin `laporan.penjualan.lihat`): insight minggu lalu (Senin–Minggu) yang sama
 * dengan pesan WhatsApp mingguan X6 (v3.79) — omzet bersih vs minggu sebelumnya, hari teramai, terlaris, produk naik &
 * turun, saran restock (hanya bila pengguna punya `persediaan.lihat`), pengingat Lebaran. Belum ada penjualan di dua
 * minggu itu = `Insight: null`. Dibatasi outlet akses pengguna.
 */
final class InsightKontroler extends DasarPemilikKontroler
{
    public function Mingguan(Request $permintaan, InsightMingguan $insight, TanggalBisnisOutlet $tanggal): JsonResponse
    {
        $isi = $insight->Susun($this->IdOutletBoleh($permintaan), $tanggal->Hitung(null, CarbonImmutable::now()));

        if ($isi !== null && ! $this->CekIzin($permintaan, IzinTenant::PersediaanLihat)) {
            $isi['Restock'] = [];
        }

        return response()->json(['Insight' => $isi]);
    }
}
