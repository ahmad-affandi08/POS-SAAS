<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\PanduanAwal;

use App\Domain\Pajak\Data\BatasBiayaLayanan;
use App\Domain\PanduanAwal\Data\DataPajakPanduan;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-01 langkah 3: konfirmasi pajak outlet. Persen biaya layanan berupa string desimal bertitik (misal "7.5"),
 * 0–10 persen (§12.1), dibandingkan dengan BigDecimal (tidak pernah float).
 */
final class SimpanPajakPanduanPermintaan extends FormRequest
{
    public const POLA_PERSEN = '/^\d{1,2}(\.\d{1,2})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'PungutPbjt' => ['required', 'boolean'],
            'BiayaLayananAktif' => ['required', 'boolean'],
            'PersenBiayaLayanan' => [
                'nullable',
                'required_if_accepted:BiayaLayananAktif',
                'string',
                'regex:'.self::POLA_PERSEN,
                function (string $atribut, mixed $nilai, Closure $gagal): void {
                    if (is_string($nilai) && preg_match(self::POLA_PERSEN, $nilai) === 1
                        && BigDecimal::of($nilai)->isGreaterThan(BatasBiayaLayanan::PERSEN_MAKSIMAL)) {
                        $gagal('Biaya layanan 0 sampai '.BatasBiayaLayanan::PERSEN_MAKSIMAL.' persen.');
                    }
                },
            ],
            'HargaTermasukPajak' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'PersenBiayaLayanan.required_if_accepted' => 'Isi persen biaya layanan.',
            'PersenBiayaLayanan.regex' => 'Biaya layanan 0 sampai '.BatasBiayaLayanan::PERSEN_MAKSIMAL.' persen.',
        ];
    }

    public function AmbilData(): DataPajakPanduan
    {
        return new DataPajakPanduan(
            pungutPbjt: $this->boolean('PungutPbjt'),
            biayaLayananAktif: $this->boolean('BiayaLayananAktif'),
            persenBiayaLayanan: $this->filled('PersenBiayaLayanan') ? $this->string('PersenBiayaLayanan')->toString() : '0',
            hargaTermasukPajak: $this->boolean('HargaTermasukPajak'),
        );
    }
}
