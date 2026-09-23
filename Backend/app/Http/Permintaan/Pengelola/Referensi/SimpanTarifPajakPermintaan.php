<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Data\DataTarifPajak;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class SimpanTarifPajakPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'KodeJenisPajak' => ['required', 'string', 'exists:JenisPajak,Kode'],
            'Tarif' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
            'PengaliDppPembilang' => ['required', 'integer', 'min:1', 'max:1000'],
            'PengaliDppPenyebut' => ['required', 'integer', 'min:1', 'max:1000'],
            'KodeWilayah' => ['nullable', 'string', 'max:13'],
            'BiayaLayananMasukDpp' => ['required', 'boolean'],
            'BerlakuMulai' => ['required', 'date_format:Y-m-d'],
            'NomorDasarHukum' => ['nullable', 'string', 'max:150'],
            'TautanDasarHukum' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Tarif.regex' => 'Tarif ditulis dalam persen dengan titik desimal, misal 10 atau 10.5.'];
    }

    public function AmbilData(): DataTarifPajak
    {
        return new DataTarifPajak(
            kodeJenisPajak: $this->string('KodeJenisPajak')->toString(),
            tarif: $this->string('Tarif')->toString(),
            pengaliDppPembilang: $this->integer('PengaliDppPembilang'),
            pengaliDppPenyebut: $this->integer('PengaliDppPenyebut'),
            kodeWilayah: $this->filled('KodeWilayah') ? trim($this->string('KodeWilayah')->toString()) : null,
            biayaLayananMasukDpp: $this->boolean('BiayaLayananMasukDpp'),
            berlakuMulai: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('BerlakuMulai')->toString()) ?: CarbonImmutable::today(),
            nomorDasarHukum: $this->filled('NomorDasarHukum') ? trim($this->string('NomorDasarHukum')->toString()) : null,
            tautanDasarHukum: $this->filled('TautanDasarHukum') ? trim($this->string('TautanDasarHukum')->toString()) : null,
        );
    }
}
