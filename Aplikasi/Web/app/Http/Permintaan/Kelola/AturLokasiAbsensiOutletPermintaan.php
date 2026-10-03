<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lokasi absensi web outlet (F-18 bagian 4, D-37): `{ Lintang, Bujur, RadiusAbsensiMeter }`, derajat desimal teks.
 */
final class AturLokasiAbsensiOutletPermintaan extends FormRequest
{
    private const POLA_KOORDINAT = '/^-?\d{1,3}(\.\d{1,12})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Lintang' => ['nullable', 'string', 'regex:'.self::POLA_KOORDINAT],
            'Bujur' => ['nullable', 'string', 'regex:'.self::POLA_KOORDINAT],
            'RadiusAbsensiMeter' => ['required', 'integer', 'min:20', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Lintang' => 'lintang', 'Bujur' => 'bujur', 'RadiusAbsensiMeter' => 'radius absensi'];
    }

    public function AmbilLintang(): ?string
    {
        return $this->filled('Lintang') ? trim($this->string('Lintang')->toString()) : null;
    }

    public function AmbilBujur(): ?string
    {
        return $this->filled('Bujur') ? trim($this->string('Bujur')->toString()) : null;
    }
}
