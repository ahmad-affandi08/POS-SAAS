<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Data\DataHargaPaket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class SimpanHargaPaketPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'HargaBulanan' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'HargaTahunan' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'BerlakuMulai' => ['required', 'date_format:Y-m-d'],
            'TerapkanKePelangganLama' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'HargaBulanan.regex' => 'Harga ditulis tanpa titik ribuan, misal 199000 atau 199000.50.',
            'HargaTahunan.regex' => 'Harga ditulis tanpa titik ribuan, misal 1910400.',
        ];
    }

    public function AmbilData(): DataHargaPaket
    {
        return new DataHargaPaket(
            hargaBulanan: $this->string('HargaBulanan')->toString(),
            hargaTahunan: $this->string('HargaTahunan')->toString(),
            berlakuMulai: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('BerlakuMulai')->toString()) ?: CarbonImmutable::today(),
            terapkanKePelangganLama: $this->boolean('TerapkanKePelangganLama'),
        );
    }
}
