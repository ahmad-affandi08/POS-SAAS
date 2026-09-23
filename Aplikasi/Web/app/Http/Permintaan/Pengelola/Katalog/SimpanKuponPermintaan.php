<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Data\DataKupon;
use App\Domain\Tenant\Enum\JenisKupon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanKuponPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'max:30'],
            'Jenis' => ['required', Rule::enum(JenisKupon::class)],
            'Nilai' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'DurasiBulan' => ['required', 'integer', 'min:1', 'max:120'],
            'Kuota' => ['nullable', 'integer', 'min:1'],
            'DaftarKodePaket' => ['nullable', 'array'],
            'DaftarKodePaket.*' => ['string', 'distinct'],
            'BerlakuSampai' => ['nullable', 'date_format:Y-m-d'],
            'Aktif' => ['required', 'boolean'],
        ];
    }

    public function AmbilData(): DataKupon
    {
        /** @var list<string> $paket */
        $paket = array_values(array_map('strval', $this->array('DaftarKodePaket')));
        $berlakuSampai = $this->filled('BerlakuSampai')
            ? (CarbonImmutable::createFromFormat('!Y-m-d', $this->string('BerlakuSampai')->toString()) ?: null)
            : null;

        return new DataKupon(
            kode: strtoupper(trim($this->string('Kode')->toString())),
            jenis: JenisKupon::from($this->string('Jenis')->toString()),
            nilai: $this->string('Nilai')->toString(),
            durasiBulan: $this->integer('DurasiBulan'),
            kuota: $this->filled('Kuota') ? $this->integer('Kuota') : null,
            daftarKodePaket: $paket === [] ? null : $paket,
            berlakuSampai: $berlakuSampai,
            aktif: $this->boolean('Aktif'),
        );
    }
}
