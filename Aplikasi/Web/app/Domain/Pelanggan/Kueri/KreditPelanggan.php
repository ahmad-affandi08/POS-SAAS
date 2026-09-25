<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\Piutang;
use Carbon\CarbonImmutable;

/**
 * Kueri publik posisi kredit pelanggan (F-12): limit kredit, sisa piutang terbuka, dan hari terlama lewat jatuh tempo;
 * dipakai pencarian pelanggan POS (cache offline), back-office, dan pemeriksaan BR-12.1 saat penjualan tempo diterima.
 */
final class KreditPelanggan
{
    /**
     * @param  list<int>  $idPelanggan
     * @return array<int, array{LimitKredit: string|null, SisaPiutang: string, HariLewatJatuhTempo: int}>
     */
    public function AmbilRingkas(array $idPelanggan, CarbonImmutable $hariIni): array
    {
        if ($idPelanggan === []) {
            return [];
        }

        $limit = [];
        $sisa = [];
        $lewat = [];

        foreach (Pelanggan::query()->whereKey($idPelanggan)->get(['Id', 'LimitKredit']) as $p) {
            $limit[$p->Id] = $p->LimitKredit === null ? null : (string) $p->LimitKredit;
            $sisa[$p->Id] = Uang::Nol();
            $lewat[$p->Id] = 0;
        }

        $terbuka = Piutang::query()
            ->whereIn('IdPelanggan', array_keys($limit))
            ->whereIn('Status', [StatusPiutang::BelumLunas->value, StatusPiutang::DibayarSebagian->value])
            ->get();

        foreach ($terbuka as $piutang) {
            $id = (int) $piutang->IdPelanggan;
            $sisa[$id] = $sisa[$id]->Tambah($piutang->AmbilSisa());
            // Bandingkan tanggal kalender (tanpa zona waktu); 0 = belum lewat jatuh tempo.
            $jatuhTempo = CarbonImmutable::parse($piutang->JatuhTempo->toDateString(), 'UTC');
            $lewat[$id] = max($lewat[$id], (int) $jatuhTempo->diffInDays(CarbonImmutable::parse($hariIni->toDateString(), 'UTC'), false));
        }

        $hasil = [];

        foreach ($limit as $id => $nilai) {
            $hasil[$id] = ['LimitKredit' => $nilai, 'SisaPiutang' => $sisa[$id]->KeString(), 'HariLewatJatuhTempo' => $lewat[$id]];
        }

        return $hasil;
    }

    /**
     * BR-12.1: alasan penjualan tempo [jumlah] butuh penyetuju (kosong = boleh tanpa penyetuju). Piutang penjualan ini
     * belum dicatat saat diperiksa.
     *
     * @return list<string>
     */
    public function Periksa(int $idPelanggan, Uang $jumlah, CarbonImmutable $hariIni, int $batasHariLewat): array
    {
        $ringkas = $this->AmbilRingkas([$idPelanggan], $hariIni)[$idPelanggan] ?? null;

        if ($ringkas === null) {
            return ['pelanggan tidak ditemukan'];
        }

        $alasan = [];
        $limit = $ringkas['LimitKredit'] === null ? null : Uang::Dari($ringkas['LimitKredit']);
        $setelah = Uang::Dari($ringkas['SisaPiutang'])->Tambah($jumlah);

        if ($limit === null || $limit->Bandingkan(Uang::Nol()) <= 0) {
            $alasan[] = 'pelanggan belum punya limit kredit';
        } elseif ($setelah->Bandingkan($limit) > 0) {
            $alasan[] = "piutang {$setelah->FormatRupiah()} melebihi limit {$limit->FormatRupiah()}";
        }

        if ($ringkas['HariLewatJatuhTempo'] > $batasHariLewat) {
            $alasan[] = "ada piutang lewat jatuh tempo {$ringkas['HariLewatJatuhTempo']} hari";
        }

        return $alasan;
    }
}
