<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Data\DataAksesAnggota;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Peran & akses outlet saat mengubah akses anggota (dan dasar isian undangan).
 */
class AksesAnggotaPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Peran' => ['required', 'string', 'size:26'],
            'SemuaOutlet' => ['boolean'],
            'Outlet' => ['array', 'max:500'],
            'Outlet.*' => ['string', 'size:26', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Peran.required' => 'Pilih peran.', 'Peran.size' => 'Pilih peran yang tersedia.'];
    }

    public function AmbilAkses(): DataAksesAnggota
    {
        /** @var list<string> $outlet */
        $outlet = array_values(array_map('strval', $this->array('Outlet')));

        return new DataAksesAnggota(
            uuidPeran: $this->string('Peran')->toString(),
            semuaOutlet: $this->boolean('SemuaOutlet'),
            uuidOutlet: $outlet,
        );
    }
}
