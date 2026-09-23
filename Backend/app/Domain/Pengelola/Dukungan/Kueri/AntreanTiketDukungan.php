<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Dukungan\Kueri;

use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Pengelola\Bersama\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Antrean tiket dukungan semua tenant di Platform Pengelola (P-09). Dibaca lewat KonteksPengelola (tercatat di log
 * audit). Tiket terbuka diurutkan dari batas SLA terdekat; saringan status, prioritas, lewat SLA, dan penanggung jawab.
 */
final class AntreanTiketDukungan
{
    public const PER_HALAMAN = 30;

    public const SARING_STATUS_TERBUKA = 'terbuka';

    public const SARING_STATUS_SEMUA = 'semua';

    public const SARING_MILIK_SAYA = 'saya';

    public const SARING_MILIK_BELUM = 'belum';

    public function __construct(
        private readonly KonteksPengelola $konteks,
        private readonly RingkasanTenant $ringkasanTenant,
    ) {}

    /**
     * @param  array{Status: string, Prioritas: string, LewatSla: bool, Milik: string, Kata: string}  $saring
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(PenggunaPengelola $pelaku, array $saring): array
    {
        return $this->konteks->JalankanLintasTenant('Membuka antrean tiket dukungan', function () use ($pelaku, $saring): array {
            $halaman = $this->BangunKueri($pelaku, $saring)->paginate(self::PER_HALAMAN, ['*'], 'halaman')->withQueryString();

            return $this->Petakan($halaman);
        });
    }

    /**
     * @param  array{Status: string, Prioritas: string, LewatSla: bool, Milik: string, Kata: string}  $saring
     * @return Builder<TiketDukungan>
     */
    private function BangunKueri(PenggunaPengelola $pelaku, array $saring): Builder
    {
        $terbuka = array_map(fn (StatusTiketDukungan $status) => $status->value, StatusTiketDukungan::AmbilTerbuka());
        $kueri = $this->konteks->KueriLintasTenant(TiketDukungan::class);

        match ($saring['Status']) {
            self::SARING_STATUS_SEMUA => null,
            self::SARING_STATUS_TERBUKA => $kueri->whereIn('Status', $terbuka),
            // Status tak dikenal menghasilkan daftar kosong, bukan semua tiket.
            default => $kueri->where('Status', StatusTiketDukungan::tryFrom($saring['Status']) === null ? '-' : $saring['Status']),
        };

        if (PrioritasTiketDukungan::tryFrom($saring['Prioritas']) !== null) {
            $kueri->where('Prioritas', $saring['Prioritas']);
        }

        if ($saring['LewatSla']) {
            $kueri->whereNull('ResponsPertamaPada')->whereIn('Status', $terbuka)->where('BatasSlaPada', '<', now());
        }

        match ($saring['Milik']) {
            self::SARING_MILIK_SAYA => $kueri->where('IdPenanggungJawab', $pelaku->Id),
            self::SARING_MILIK_BELUM => $kueri->whereNull('IdPenanggungJawab'),
            default => null,
        };

        if ($saring['Kata'] !== '') {
            $kata = '%'.addcslashes($saring['Kata'], '%_\\').'%';
            $kueri->where(fn (Builder $bagian) => $bagian->where('Nomor', 'like', $kata)->orWhere('Judul', 'like', $kata));
        }

        return $saring['Status'] === self::SARING_STATUS_SEMUA || $saring['Status'] === StatusTiketDukungan::Selesai->value
            || $saring['Status'] === StatusTiketDukungan::Ditutup->value
            ? $kueri->orderByDesc('Id')
            : $kueri->orderBy('BatasSlaPada')->orderBy('Id');
    }

    /**
     * @param  LengthAwarePaginator<int, TiketDukungan>  $halaman
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    private function Petakan(LengthAwarePaginator $halaman): array
    {
        /** @var list<TiketDukungan> $tiket */
        $tiket = $halaman->items();
        $tenant = collect($this->ringkasanTenant->Ambil(array_values(array_unique(array_map(fn (TiketDukungan $baris) => $baris->IdTenant, $tiket)))))
            ->keyBy('Id');
        $idPenanggungJawab = array_values(array_filter(array_unique(array_map(fn (TiketDukungan $baris) => $baris->IdPenanggungJawab, $tiket))));
        $namaPenanggungJawab = $idPenanggungJawab === []
            ? collect()
            : PenggunaPengelola::query()->whereKey($idPenanggungJawab)->pluck('Nama', 'Id');
        $sekarang = now();

        return [
            'Data' => array_map(fn (TiketDukungan $baris): array => [
                'Uuid' => $baris->Uuid,
                'Nomor' => $baris->Nomor,
                'Judul' => $baris->Judul,
                'NamaTenant' => $tenant->get($baris->IdTenant)['Nama'] ?? "Tenant #{$baris->IdTenant}",
                'Kategori' => $baris->Kategori->AmbilLabel(),
                'Prioritas' => $baris->Prioritas->value,
                'LabelPrioritas' => $baris->Prioritas->AmbilLabel(),
                'Status' => $baris->Status->value,
                'LabelStatus' => $baris->Status->AmbilLabel(),
                'PenanggungJawab' => $baris->IdPenanggungJawab === null ? null : (string) ($namaPenanggungJawab[$baris->IdPenanggungJawab] ?? '-'),
                'BatasSlaPada' => $baris->BatasSlaPada->toIso8601String(),
                'ResponsPertamaPada' => $baris->ResponsPertamaPada?->toIso8601String(),
                'LewatSla' => $baris->CekLewatSla($sekarang),
                'PesanTerakhirPada' => $baris->PesanTerakhirPada?->toIso8601String(),
                'DibuatPada' => $baris->DibuatPada->toIso8601String(),
            ], $tiket),
            'HalamanSaatIni' => $halaman->currentPage(),
            'HalamanTerakhir' => $halaman->lastPage(),
            'Total' => $halaman->total(),
        ];
    }
}
