<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\PanduanAwal;

use App\Domain\PanduanAwal\Data\DataProfilUsaha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * F-01 langkah 1. Dikirim multipart (`forceFormData`): boolean "1"/"0", string kosong menjadi null.
 * NPWP dinormalkan menjadi angka saja sebelum divalidasi (15 atau 16 angka).
 */
final class SimpanProfilUsahaPermintaan extends FormRequest
{
    public const POLA_NPWP = '/^\d{15,16}$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'NamaUsaha' => ['required', 'string', 'max:150'],
            'Alamat' => ['nullable', 'string', 'max:500'],
            'KodeKota' => ['required', 'string', 'max:20'],
            'Npwp' => ['nullable', 'required_if_accepted:Pkp', 'string', 'regex:'.self::POLA_NPWP],
            'Pkp' => ['required', 'boolean'],
            'Logo' => ['nullable', 'file', 'mimes:'.implode(',', (array) config('tenant.EkstensiLogo')), 'max:'.config('tenant.UkuranMaksimalLogoKb')],
            'HapusLogo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'NamaUsaha.required' => 'Isi nama usaha.',
            'KodeKota.required' => 'Pilih kabupaten/kota outlet.',
            'Npwp.regex' => 'NPWP berisi 15 atau 16 angka.',
            'Npwp.required_if_accepted' => 'Usaha PKP wajib mengisi NPWP.',
            'Logo.mimes' => 'Logo berupa gambar PNG, JPG, atau WEBP.',
            'Logo.max' => 'Ukuran logo maksimal '.config('tenant.UkuranMaksimalLogoKb').' KB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $npwp = $this->input('Npwp');

        if (is_string($npwp)) {
            $angka = (string) preg_replace('/\D+/', '', $npwp);
            $this->merge(['Npwp' => $angka === '' ? null : $angka]);
        }
    }

    public function AmbilData(): DataProfilUsaha
    {
        return new DataProfilUsaha(
            namaUsaha: $this->string('NamaUsaha')->trim()->toString(),
            alamat: $this->filled('Alamat') ? $this->string('Alamat')->trim()->toString() : null,
            kodeKota: $this->string('KodeKota')->toString(),
            npwp: $this->filled('Npwp') ? $this->string('Npwp')->toString() : null,
            pkp: $this->boolean('Pkp'),
        );
    }

    public function AmbilLogo(): ?UploadedFile
    {
        $logo = $this->file('Logo');

        return $logo instanceof UploadedFile ? $logo : null;
    }
}
