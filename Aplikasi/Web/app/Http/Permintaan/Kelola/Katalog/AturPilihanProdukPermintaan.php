<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Kelompok pilihan yang dipasang ke produk, berurutan: `{ KelompokPilihan: string[] }` (Uuid, F-03 E.9). Kelompok
 * tenant lain = tidak ditemukan.
 */
final class AturPilihanProdukPermintaan extends FormRequest
{
    /** @var list<int> */
    private array $idKelompok = [];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'KelompokPilihan' => ['present', 'array', 'max:50'],
            'KelompokPilihan.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $uuid = array_values(array_filter((array) $this->input('KelompokPilihan', []), 'is_string'));
            $peta = KelompokPilihan::query()->whereIn('Uuid', $uuid)->pluck('Id', 'Uuid');

            foreach ($uuid as $i => $isi) {
                $id = $peta->get($isi);

                if (! is_numeric($id)) {
                    $validator->errors()->add("KelompokPilihan.{$i}", 'Kelompok pilihan tidak ditemukan.');

                    continue;
                }

                $this->idKelompok[] = (int) $id;
            }
        });
    }

    /**
     * @return list<int>
     */
    public function AmbilIdKelompokPilihan(): array
    {
        return $this->idKelompok;
    }
}
