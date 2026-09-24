<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batch outbox POS (PRD §16.3, §18): maks. 50 item berurutan, masing-masing `{Jenis, Uuid (ULID), Data}`. Bentuk
 * `Data` diperiksa penangan per jenis, sehingga satu item rusak hanya menolak item itu.
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
        ];
    }

    /**
     * @return list<array{Jenis: string, Uuid: string, Data: array<string, mixed>}>
     */
    public function AmbilItem(): array
    {
        $hasil = [];

        foreach ((array) $this->validated('Item') as $item) {
            $hasil[] = [
                'Jenis' => (string) $item['Jenis'],
                'Uuid' => strtoupper((string) $item['Uuid']),
                'Data' => (array) $item['Data'],
            ];
        }

        return $hasil;
    }
}
