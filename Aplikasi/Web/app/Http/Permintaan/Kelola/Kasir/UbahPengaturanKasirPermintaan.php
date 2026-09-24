<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Kasir;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Tenant\Data\DataPengaturanKasir;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isian pengaturan kasir (F-06, F-07b). Bidang F-07b (`BatasDiskonManual`, `BatasDiskonPenyetuju`,
 * `PembulatanTunai`) boleh tidak dikirim: nilai tersimpan dipertahankan. `PembulatanTunai` null = tanpa pembulatan.
 */
final class UbahPengaturanKasirPermintaan extends FormRequest
{
    private const POLA_PERSEN = '/^\d{1,3}(\.\d{1,2})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'BatasKasKeluar' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'ShiftBersama' => ['required', 'boolean'],
            'BatasDiskonManual' => ['sometimes', 'required', 'string', 'regex:'.self::POLA_PERSEN],
            'BatasDiskonPenyetuju' => ['sometimes', 'required', 'string', 'regex:'.self::POLA_PERSEN],
            'PembulatanTunai' => ['sometimes', 'nullable', 'array'],
            'PembulatanTunai.Kelipatan' => ['required_with:PembulatanTunai', 'integer', 'min:1', 'max:1000000'],
            'PembulatanTunai.Arah' => ['required_with:PembulatanTunai', 'string', Rule::enum(ArahPembulatan::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'BatasKasKeluar.regex' => 'Batas kas keluar harus berupa nominal rupiah, misal 200000.',
            'BatasDiskonManual.regex' => 'Batas diskon kasir berupa persen 0 sampai 100, misal 10.',
            'BatasDiskonPenyetuju.regex' => 'Batas diskon dengan persetujuan berupa persen 0 sampai 100, misal 30.',
            'PembulatanTunai.Kelipatan.*' => 'Kelipatan pembulatan berupa bilangan bulat Rupiah, misal 100.',
            'PembulatanTunai.Arah.*' => 'Pilih arah pembulatan.',
        ];
    }

    /** Pengaturan baru; bidang F-07b yang tidak dikirim memakai nilai `lama`. */
    public function AmbilData(DataPengaturanKasir $lama): DataPengaturanKasir
    {
        $pembulatan = $lama->pembulatanTunai;

        if ($this->has('PembulatanTunai')) {
            $pembulatan = $this->filled('PembulatanTunai.Kelipatan')
                ? ['Kelipatan' => $this->integer('PembulatanTunai.Kelipatan'), 'Arah' => ArahPembulatan::from($this->string('PembulatanTunai.Arah')->toString())]
                : null;
        }

        return new DataPengaturanKasir(
            Uang::Dari((string) $this->validated('BatasKasKeluar')),
            $this->boolean('ShiftBersama'),
            $this->has('BatasDiskonManual') ? $this->string('BatasDiskonManual')->toString() : $lama->batasDiskonManual,
            $this->has('BatasDiskonPenyetuju') ? $this->string('BatasDiskonPenyetuju')->toString() : $lama->batasDiskonPenyetuju,
            $pembulatan,
        );
    }
}
