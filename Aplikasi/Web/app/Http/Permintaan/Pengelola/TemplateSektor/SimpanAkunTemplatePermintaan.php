<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\TemplateSektor;

use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Pajak\Enum\DasarPengenaanPajak;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bentuk COA, pemetaan akun, dan kelompok pajak template (BR-P03.5). Konsistensi COA dan pemetaan dinilai
 * ValidatorTemplate (BR-P03.3) agar draf yang belum lengkap tetap bisa disimpan.
 */
final class SimpanAkunTemplatePermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Akun' => ['present', 'array', 'max:300'],
            'Akun.*.Kode' => ['required', 'string', 'max:10'],
            'Akun.*.Nama' => ['required', 'string', 'max:100'],
            'Akun.*.Tipe' => ['required', 'string', Rule::enum(TipeAkun::class)],
            'Akun.*.SaldoNormal' => ['required', 'string', Rule::enum(SaldoNormal::class)],
            'Akun.*.Kontra' => ['required', 'boolean'],
            'PemetaanAkun' => ['present', 'array'],
            'PemetaanAkun.*' => ['nullable', 'string', 'max:10'],
            'KelompokPajak' => ['present', 'array', 'max:20'],
            'KelompokPajak.*.Nama' => ['required', 'string', 'max:60'],
            'KelompokPajak.*.Detail' => ['present', 'array', 'max:5'],
            'KelompokPajak.*.Detail.*.KodeJenisPajak' => ['required', 'string', 'max:50'],
            'KelompokPajak.*.Detail.*.DasarPengenaan' => ['required', 'string', Rule::enum(DasarPengenaanPajak::class)],
            'KelompokPajak.*.Detail.*.Urutan' => ['required', 'integer', 'min:1', 'max:9'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilIsi(): array
    {
        $akun = array_map(fn (array $baris): array => [
            'Kode' => trim((string) $baris['Kode']),
            'Nama' => trim((string) $baris['Nama']),
            'Tipe' => (string) $baris['Tipe'],
            'SaldoNormal' => (string) $baris['SaldoNormal'],
            'Kontra' => filter_var($baris['Kontra'], FILTER_VALIDATE_BOOLEAN),
        ], array_values(array_filter((array) $this->input('Akun', []), 'is_array')));
        usort($akun, fn (array $a, array $b) => strcmp($a['Kode'], $b['Kode']));

        $pemetaan = [];

        foreach ((array) $this->input('PemetaanAkun', []) as $peran => $kode) {
            if (is_string($kode) && $kode !== '') {
                $pemetaan[(string) $peran] = $kode;
            }
        }

        ksort($pemetaan);

        $kelompok = array_map(fn (array $baris): array => [
            'Nama' => trim((string) $baris['Nama']),
            'Detail' => array_map(fn (array $detail): array => [
                'KodeJenisPajak' => (string) $detail['KodeJenisPajak'],
                'DasarPengenaan' => (string) $detail['DasarPengenaan'],
                'Urutan' => (int) $detail['Urutan'],
            ], array_values(array_filter((array) ($baris['Detail'] ?? []), 'is_array'))),
        ], array_values(array_filter((array) $this->input('KelompokPajak', []), 'is_array')));

        return ['Akun' => $akun, 'PemetaanAkun' => $pemetaan, 'KelompokPajak' => $kelompok];
    }
}
