<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Akuntansi\Data\DataGiroMasukan;
use Carbon\CarbonImmutable;

/**
 * v3.42: aturan isian cara bayar (kas/bank atau giro/cek mundur) bersama untuk pelunasan piutang & pembayaran hutang.
 * `CaraBayar` = `KasBank` (bawaan) atau `Giro`; `UuidAkun` wajib hanya untuk kas/bank.
 */
final class AturanGiro
{
    /**
     * @return array<string, list<string>>
     */
    public static function Aturan(): array
    {
        return [
            'CaraBayar' => ['nullable', 'string', 'in:KasBank,Giro'],
            'UuidAkun' => ['required_unless:CaraBayar,Giro', 'nullable', 'string', 'ulid'],
            'Giro' => ['required_if:CaraBayar,Giro', 'nullable', 'array'],
            'Giro.NomorGiro' => ['required_if:CaraBayar,Giro', 'nullable', 'string', 'max:40'],
            'Giro.NamaBank' => ['required_if:CaraBayar,Giro', 'nullable', 'string', 'max:80'],
            'Giro.TanggalJatuhTempo' => ['required_if:CaraBayar,Giro', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function Atribut(): array
    {
        return ['Giro.NomorGiro' => 'nomor giro', 'Giro.NamaBank' => 'bank penerbit giro', 'Giro.TanggalJatuhTempo' => 'tanggal efektif giro'];
    }

    /**
     * @param  array<string, mixed>  $data  hasil validasi
     */
    public static function AmbilGiro(array $data): ?DataGiroMasukan
    {
        $giro = $data['Giro'] ?? null;

        if (($data['CaraBayar'] ?? 'KasBank') !== 'Giro' || ! is_array($giro)) {
            return null;
        }

        return new DataGiroMasukan(
            (string) ($giro['NomorGiro'] ?? ''),
            (string) ($giro['NamaBank'] ?? ''),
            CarbonImmutable::createFromFormat('!Y-m-d', (string) ($giro['TanggalJatuhTempo'] ?? '')) ?: CarbonImmutable::today(),
        );
    }
}
