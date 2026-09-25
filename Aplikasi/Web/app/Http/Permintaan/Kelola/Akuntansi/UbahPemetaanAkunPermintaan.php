<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Akuntansi;

use App\Domain\Akuntansi\Enum\PeranAkun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isian pemetaan akun (F-13a): peran akun, outlet (kosong = tingkat tenant), akun. `UuidAkun` hanya wajib saat
 * menyimpan (bukan saat menghapus override outlet).
 */
final class UbahPemetaanAkunPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kunci' => ['required', 'string', Rule::enum(PeranAkun::class)],
            'UuidOutlet' => [$this->isMethod('DELETE') ? 'required' : 'nullable', 'string', 'ulid'],
            'UuidAkun' => [$this->isMethod('DELETE') ? 'prohibited' : 'required', 'string', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Kunci' => 'peran akun', 'UuidOutlet' => 'outlet', 'UuidAkun' => 'akun'];
    }

    public function AmbilPeran(): PeranAkun
    {
        return PeranAkun::from((string) $this->validated('Kunci'));
    }

    public function AmbilUuidOutlet(): ?string
    {
        $uuid = $this->validated('UuidOutlet');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }
}
