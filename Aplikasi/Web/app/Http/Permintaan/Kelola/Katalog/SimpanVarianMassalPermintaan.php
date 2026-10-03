<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Uang;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Audit kemudahan pakai #18 `PUT /kelola/produk/{produk}/varian`: `{Baris: [{Uuid, Harga?, Barcode?}]}`. Harga kosong =
 * tidak diubah; barcode kosong = tidak ditambah. Izin `produk.kelola` dijaga rute; izin harga diperiksa Aksi.
 */
final class SimpanVarianMassalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Baris' => ['required', 'array', 'min:1', 'max:500'],
            'Baris.*.Uuid' => ['required', 'ulid'],
            'Baris.*.Harga' => ['nullable', 'string', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'Baris.*.Barcode' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Baris.*.Harga' => 'harga', 'Baris.*.Barcode' => 'barcode'];
    }

    /**
     * @return list<array{Uuid: string, Harga: ?Uang, Barcode: ?string}>
     */
    public function AmbilBaris(): array
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));

        return array_map(fn (array $b): array => [
            'Uuid' => (string) $b['Uuid'],
            'Harga' => isset($b['Harga']) && $b['Harga'] !== '' ? Uang::Dari((string) $b['Harga']) : null,
            'Barcode' => isset($b['Barcode']) && trim((string) $b['Barcode']) !== '' ? trim((string) $b['Barcode']) : null,
        ], $baris);
    }
}
