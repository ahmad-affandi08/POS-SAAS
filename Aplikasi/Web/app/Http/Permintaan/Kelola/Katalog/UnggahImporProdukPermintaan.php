<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Impor\Layanan\PembacaPresetImpor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Unggah berkas impor produk F-03 (E.10): multipart `Berkas` ≤ `katalog.Impor.UkuranMaksimalKb` dan `Sumber` = kode
 * preset. Jenis berkas (xlsx/csv) diperiksa dari isinya di `UnggahBerkasImpor`, bukan dari nama/MIME kiriman klien.
 */
final class UnggahImporProdukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Berkas' => ['required', 'file', 'max:'.(int) config('katalog.Impor.UkuranMaksimalKb', 10240)],
            'Sumber' => ['required', 'string', Rule::in(app(PembacaPresetImpor::class)->AmbilKode())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Berkas.required' => 'Pilih berkas Excel atau CSV lebih dulu.',
            'Berkas.file' => 'Berkas gagal diunggah. Coba lagi.',
            'Berkas.uploaded' => 'Berkas gagal diunggah. Periksa ukuran berkas lalu coba lagi.',
            'Berkas.max' => 'Ukuran berkas maksimal '.intdiv((int) config('katalog.Impor.UkuranMaksimalKb', 10240), 1024).' MB. Bagi berkas menjadi beberapa bagian.',
            'Sumber.required' => 'Pilih format berkas.',
            'Sumber.in' => 'Format berkas tidak dikenal.',
        ];
    }

    public function AmbilBerkas(): UploadedFile
    {
        $berkas = $this->file('Berkas');
        abort_unless($berkas instanceof UploadedFile, 422);

        return $berkas;
    }
}
