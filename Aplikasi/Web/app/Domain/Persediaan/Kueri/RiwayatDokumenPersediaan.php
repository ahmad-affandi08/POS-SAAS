<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use Closure;

/**
 * Riwayat status dokumen persediaan F-05b (tipe FE `RiwayatDokumenPersediaan[]`) dan nama pengguna untuk kolom
 * "oleh" di detail dokumen.
 */
final class RiwayatDokumenPersediaan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @param  Closure(string): string  $label  label status
     * @param  list<int|null>  $idLain  pengguna lain yang namanya ikut dimuat
     * @return array{0: list<array{StatusDari: string|null, StatusKe: string, LabelStatusKe: string, Oleh: string|null, Pada: string, Alasan: string|null}>, 1: Closure(int|null): (string|null)}
     */
    public function Ambil(string $jenisDokumen, int $idDokumen, Closure $label, array $idLain = []): array
    {
        $riwayat = RiwayatStatusDokumen::query()->where('JenisDokumen', $jenisDokumen)->where('IdDokumen', $idDokumen)->orderBy('Id')->get();
        $id = array_values(array_unique(array_filter([...$idLain, ...$riwayat->pluck('DiubahOleh')->all()], 'is_int')));
        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $id);
        $namaDari = fn (?int $idPengguna): ?string => $idPengguna === null ? null : ($nama[$idPengguna] ?? null);

        return [array_values($riwayat->map(fn (RiwayatStatusDokumen $r): array => [
            'StatusDari' => $r->StatusDari,
            'StatusKe' => $r->StatusKe,
            'LabelStatusKe' => $label($r->StatusKe),
            'Oleh' => $namaDari($r->DiubahOleh),
            'Pada' => $r->DiubahPada?->toIso8601String() ?? '',
            'Alasan' => $r->Alasan,
        ])->all()), $namaDari];
    }
}
