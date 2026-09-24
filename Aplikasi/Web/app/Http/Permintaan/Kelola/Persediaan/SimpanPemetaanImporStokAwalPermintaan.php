<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Persediaan\Enum\BidangImporStokAwal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pemetaan kolom impor stok awal (DesainF05a D): `{ Pemetaan: Record<BidangImporStokAwal, number|null>;
 * UuidGudangBawaan: string|null; Tanggal: 'YYYY-MM-DD' }`. Aturan pemetaan (bidang wajib, kolom tidak ganda,
 * tanggal tidak melewati hari ini) di `SimpanPemetaanImporStokAwal`; akses lokasi stok di kontroler (404).
 */
final class SimpanPemetaanImporStokAwalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Pemetaan' => ['required', 'array'],
            'Pemetaan.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'UuidGudangBawaan' => ['nullable', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Pemetaan.*.integer' => 'Pilih kolom dari daftar.',
            'UuidGudangBawaan.ulid' => 'Lokasi stok tidak dikenal. Muat ulang halaman lalu pilih lagi.',
            'Tanggal.required' => 'Isi tanggal stok awal.',
            'Tanggal.date_format' => 'Tanggal stok awal harus berformat tanggal yang sah.',
        ];
    }

    /**
     * @return array<string, int|null>
     */
    public function AmbilPemetaan(): array
    {
        $masukan = (array) $this->input('Pemetaan', []);
        $hasil = [];

        foreach (BidangImporStokAwal::cases() as $bidang) {
            $nilai = $masukan[$bidang->value] ?? null;
            $hasil[$bidang->value] = is_numeric($nilai) ? (int) $nilai : null;
        }

        return $hasil;
    }

    public function AmbilUuidGudangBawaan(): ?string
    {
        $uuid = $this->input('UuidGudangBawaan');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function AmbilTanggal(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('Tanggal')->toString()) ?: CarbonImmutable::today();
    }
}
