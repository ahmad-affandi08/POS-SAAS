<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Unggah gambar produk F-03 (E.4): multipart `Gambar` jpg/png/webp ≤ `katalog.Gambar.UkuranMaksimalKb`.
 * Ukuran piksel & isi berkas diperiksa ulang di `PenyimpanGambarProduk`.
 */
final class UnggahGambarProdukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Gambar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('katalog.Gambar.UkuranMaksimalKb', 5120)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Gambar.mimes' => 'Unggah gambar JPG, PNG, atau WebP.',
            'Gambar.max' => 'Ukuran gambar maksimal '.(int) config('katalog.Gambar.UkuranMaksimalKb', 5120).' KB.',
        ];
    }

    public function AmbilBerkas(): UploadedFile
    {
        $berkas = $this->file('Gambar');
        abort_unless($berkas instanceof UploadedFile, 422);

        return $berkas;
    }
}
