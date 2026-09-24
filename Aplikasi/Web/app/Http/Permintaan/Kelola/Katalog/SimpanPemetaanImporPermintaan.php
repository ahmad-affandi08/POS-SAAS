<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Data\DataOpsiImpor;
use App\Domain\Katalog\Impor\Enum\BidangImpor;
use App\Domain\Katalog\Impor\Enum\ModeImpor;
use App\Domain\Katalog\Layanan\OpsiKelompokPajakKatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Pemetaan kolom impor F-03 (E.10): `{ Pemetaan: Record<BidangImpor, number|null>; Opsi: OpsiImpor }`.
 * Kelompok pajak bawaan lewat ULID publik (tenant aktif saja). Aturan pemetaan lain (Nama wajib, kolom tidak ganda,
 * kolom harga tanpa izin diabaikan) di `SimpanPemetaanImpor`.
 */
final class SimpanPemetaanImporPermintaan extends FormRequest
{
    private ?int $idKelompokPajak = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $jenis = array_map(fn (JenisProduk $j): string => $j->value, array_filter(JenisProduk::cases(), fn (JenisProduk $j): bool => $j !== JenisProduk::IndukVarian));

        return [
            'Pemetaan' => ['required', 'array'],
            'Pemetaan.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'Opsi' => ['required', 'array'],
            'Opsi.Mode' => ['required', Rule::enum(ModeImpor::class)],
            'Opsi.UuidKelompokPajakBawaan' => ['nullable', 'string', 'ulid'],
            'Opsi.JenisBawaan' => ['required', Rule::in(array_values($jenis))],
            'Opsi.BuatKategoriBaru' => ['required', 'boolean'],
            'Opsi.BuatSatuanBaru' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Opsi.JenisBawaan.in' => 'Pilih jenis bawaan selain induk varian.',
            'Pemetaan.*.integer' => 'Pilih kolom dari daftar.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $uuid = $this->input('Opsi.UuidKelompokPajakBawaan');

            if (is_string($uuid) && $uuid !== '') {
                $this->idKelompokPajak = app(OpsiKelompokPajakKatalog::class)->CariIdBerdasarkanUuid($uuid);

                if ($this->idKelompokPajak === null) {
                    $validator->errors()->add('Opsi.UuidKelompokPajakBawaan', 'Kelompok pajak tidak ditemukan. Muat ulang halaman lalu pilih lagi.');
                }
            }
        });
    }

    /**
     * @return array<string, int|null>
     */
    public function AmbilPemetaan(): array
    {
        $masukan = (array) $this->input('Pemetaan', []);
        $hasil = [];

        foreach (BidangImpor::cases() as $bidang) {
            $nilai = $masukan[$bidang->value] ?? null;
            $hasil[$bidang->value] = is_numeric($nilai) ? (int) $nilai : null;
        }

        return $hasil;
    }

    public function AmbilOpsi(): DataOpsiImpor
    {
        return new DataOpsiImpor(
            ModeImpor::from($this->string('Opsi.Mode')->toString()),
            $this->idKelompokPajak,
            JenisProduk::from($this->string('Opsi.JenisBawaan')->toString()),
            $this->boolean('Opsi.BuatKategoriBaru'),
            $this->boolean('Opsi.BuatSatuanBaru'),
        );
    }
}
