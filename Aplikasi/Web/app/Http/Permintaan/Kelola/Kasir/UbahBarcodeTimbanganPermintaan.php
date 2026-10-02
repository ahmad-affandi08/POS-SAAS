<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Kasir;

use App\Domain\Tenant\Kueri\PengaturanBarcodeTimbanganTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Barcode timbangan (v3.55): `{ Aktif, Awalan: string[], Nilai: Berat|Harga }`. Awalan 21–29 (20 = barcode internal).
 */
final class UbahBarcodeTimbanganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Aktif' => ['required', 'boolean'],
            'Awalan' => ['required_if:Aktif,true', 'array', 'max:9'],
            'Awalan.*' => ['required', 'string', 'distinct', Rule::in(PengaturanBarcodeTimbanganTenant::AWALAN_BOLEH)],
            'Nilai' => ['required', 'string', Rule::in([PengaturanBarcodeTimbanganTenant::NILAI_BERAT, PengaturanBarcodeTimbanganTenant::NILAI_HARGA])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Awalan.required_if' => 'Pilih minimal satu awalan barcode timbangan.',
            'Awalan.*.in' => 'Awalan barcode timbangan 21 sampai 29 (20 dipakai barcode internal produk).',
        ];
    }

    /** @return list<string> */
    public function AmbilAwalan(): array
    {
        /** @var list<string> $awalan */
        $awalan = $this->validated('Awalan') ?? [];

        return array_values($awalan);
    }
}
