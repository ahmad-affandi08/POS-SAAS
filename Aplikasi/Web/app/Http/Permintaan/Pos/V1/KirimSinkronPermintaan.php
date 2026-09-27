<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batch outbox POS (PRD §16.3, §18): maks. 50 item berurutan, masing-masing `{Jenis, Uuid (ULID), Data,
 * UuidPerangkatAsal?}`. Bentuk `Data` diperiksa penangan per jenis, sehingga satu item rusak hanya menolak item itu.
 * `UuidPerangkatAsal` (opsional, audit P0 F-01) = perangkat yang membuat item bila berbeda dengan pengirim.
 */
final class KirimSinkronPermintaan extends FormRequest
{
    public const MAKS_ITEM = 50;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Item' => ['required', 'array', 'min:1', 'max:'.self::MAKS_ITEM],
            'Item.*.Jenis' => ['required', 'string', 'max:50'],
            'Item.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Item.*.Data' => ['present', 'array'],
            'Item.*.UuidPerangkatAsal' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return list<array{Jenis: string, Uuid: string, Data: array<string, mixed>, UuidPerangkatAsal: string|null}>
     */
    public function AmbilItem(): array
    {
        $hasil = [];

        foreach ((array) $this->validated('Item') as $item) {
            $hasil[] = [
                'Jenis' => (string) $item['Jenis'],
                'Uuid' => strtoupper((string) $item['Uuid']),
                'Data' => (array) $item['Data'],
                'UuidPerangkatAsal' => isset($item['UuidPerangkatAsal']) && is_string($item['UuidPerangkatAsal']) ? strtoupper($item['UuidPerangkatAsal']) : null,
            ];
        }

        return $hasil;
    }
}
