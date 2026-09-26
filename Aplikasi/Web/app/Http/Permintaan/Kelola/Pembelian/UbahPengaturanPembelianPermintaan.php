<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembelian;

use App\Domain\Tenant\Data\DataPengaturanPembelian;
use Illuminate\Foundation\Http\FormRequest;

/** Isian pengaturan pembelian (F-04 fase 1): batas persetujuan PO (Rupiah) dan toleransi penerimaan (persen). */
final class UbahPengaturanPembelianPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'BatasPersetujuanPo' => ['required', 'string', AturanPembelian::UANG],
            'ToleransiPenerimaanPersen' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'DrafPoOtomatis' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AturanPembelian::Pesan();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['BatasPersetujuanPo' => 'batas persetujuan PO', 'ToleransiPenerimaanPersen' => 'toleransi penerimaan'];
    }

    public function AmbilData(): DataPengaturanPembelian
    {
        return new DataPengaturanPembelian(
            AturanPembelian::Uang($this->validated('BatasPersetujuanPo')),
            (string) $this->validated('ToleransiPenerimaanPersen'),
            $this->boolean('DrafPoOtomatis', true),
        );
    }
}
