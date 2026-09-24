<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Unggah berkas impor stok awal (DesainF05a D): multipart `Berkas` ≤ `persediaan.Impor.UkuranMaksimalKb` dan
 * `UuidGudangBawaan` (ULID, opsional). Jenis berkas (xlsx/csv) diperiksa dari isinya di `UnggahImporStokAwal`, bukan
 * dari nama/MIME kiriman klien; akses lokasi stok diperiksa kontroler (lokasi di luar akses = 404).
 */
final class UnggahImporStokAwalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Berkas' => ['required', 'file', 'max:'.(int) config('persediaan.Impor.UkuranMaksimalKb', 10240)],
            'UuidGudangBawaan' => ['nullable', 'string', 'ulid'],
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
            'Berkas.max' => 'Ukuran berkas maksimal '.intdiv((int) config('persediaan.Impor.UkuranMaksimalKb', 10240), 1024).' MB. Bagi berkas menjadi beberapa bagian.',
            'UuidGudangBawaan.ulid' => 'Lokasi stok tidak dikenal. Muat ulang halaman lalu pilih lagi.',
        ];
    }

    public function AmbilBerkas(): UploadedFile
    {
        $berkas = $this->file('Berkas');
        abort_unless($berkas instanceof UploadedFile, 422);

        return $berkas;
    }

    public function AmbilUuidGudangBawaan(): ?string
    {
        $uuid = $this->input('UuidGudangBawaan');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }
}
