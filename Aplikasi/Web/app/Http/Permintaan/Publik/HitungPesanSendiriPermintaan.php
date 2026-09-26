<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Publik;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 `POST /{slugTenant}/meja/{tokenMeja}/hitung`: `{Baris: [{UuidProduk, UuidVarian?, Jumlah, Pilihan: [UuidPilihan]}]}`.
 * `UuidVarian` = anak varian bila `UuidProduk` induk varian (PRD v2.06). Harga dari peramban (bila dikirim) diabaikan.
 */
final class HitungPesanSendiriPermintaan extends FormRequest
{
    public const BATAS_BARIS = 30;

    public const BATAS_JUMLAH = 50;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Baris' => ['present', 'array', 'max:'.self::BATAS_BARIS],
            'Baris.*' => ['array'],
            'Baris.*.UuidProduk' => ['required', 'ulid'],
            'Baris.*.UuidVarian' => ['sometimes', 'nullable', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'integer', 'min:1', 'max:'.self::BATAS_JUMLAH],
            'Baris.*.Pilihan' => ['sometimes', 'array', 'max:20'],
            'Baris.*.Pilihan.*' => ['ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Baris.max' => 'Paling banyak '.self::BATAS_BARIS.' menu dalam satu pesanan.',
            'Baris.*.Jumlah.min' => 'Jumlah minimal 1.',
            'Baris.*.Jumlah.max' => 'Jumlah paling banyak '.self::BATAS_JUMLAH.' per menu.',
        ];
    }

    /**
     * @return list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian: string|null}>
     */
    public function AmbilBaris(): array
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));

        return array_map(fn (array $b): array => [
            'UuidProduk' => strtoupper((string) $b['UuidProduk']),
            'Jumlah' => (int) $b['Jumlah'],
            'Pilihan' => array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) ($b['Pilihan'] ?? []))),
            'UuidVarian' => is_string($b['UuidVarian'] ?? null) ? strtoupper($b['UuidVarian']) : null,
        ], $baris);
    }
}
