<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\TemplateSektor;

use App\Domain\Laporan\Enum\LaporanUnggulan;
use App\Domain\Pengelola\TemplateSektor\Enum\JenisProdukContoh;
use App\Domain\Pengelola\TemplateSektor\Layanan\ValidatorTemplate;
use App\Domain\Penjualan\Enum\ArahPembulatan;
use App\Domain\Penjualan\Enum\ModeKasir;
use App\Domain\Persediaan\Enum\MetodeHpp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bentuk isi bisnis template (BR-P03.5), termasuk produk contoh (DesainF01 C3; harga berupa string desimal). Kebenaran isi (fitur ada di katalog, satuan aktif, nama ganda) dinilai
 * ValidatorTemplate agar draf yang belum lengkap tetap bisa disimpan.
 */
final class SimpanIsiBisnisTemplatePermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ModeKasir' => ['present', 'array', 'max:5'],
            'ModeKasir.*' => ['string', Rule::enum(ModeKasir::class)],
            'ModeKasirDefault' => ['nullable', 'string', Rule::enum(ModeKasir::class)],
            'KunciFitur' => ['present', 'array', 'max:100'],
            'KunciFitur.*' => ['string', 'max:100'],
            'Kategori' => ['present', 'array', 'max:50'],
            'Kategori.*' => ['string', 'max:60'],
            'KodeSatuan' => ['present', 'array', 'max:50'],
            'KodeSatuan.*' => ['string', 'max:20'],
            'StasiunDapur' => ['present', 'array', 'max:20'],
            'StasiunDapur.*' => ['string', 'max:60'],
            'AlasanVoid' => ['present', 'array', 'max:20'],
            'AlasanVoid.*' => ['string', 'max:60'],
            'AlasanPenyesuaian' => ['present', 'array', 'max:20'],
            'AlasanPenyesuaian.*' => ['string', 'max:60'],
            'LaporanUnggulan' => ['present', 'array', 'max:8'],
            'LaporanUnggulan.*' => ['string', Rule::enum(LaporanUnggulan::class)],
            'Pengaturan' => ['required', 'array'],
            'Pengaturan.PembulatanTunai' => ['required', 'array'],
            'Pengaturan.PembulatanTunai.Kelipatan' => ['required', 'integer', 'min:1', 'max:1000000'],
            'Pengaturan.PembulatanTunai.Arah' => ['required', 'string', Rule::enum(ArahPembulatan::class)],
            'Pengaturan.PersenBiayaLayanan' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'Pengaturan.BiayaLayananMasukDpp' => ['required', 'boolean'],
            'Pengaturan.StokBolehMinus' => ['required', 'boolean'],
            'Pengaturan.MetodeHpp' => ['required', 'string', Rule::enum(MetodeHpp::class)],
            'Pengaturan.HargaTermasukPajak' => ['required', 'boolean'],
            'ProdukContoh' => ['present', 'array', 'max:'.ValidatorTemplate::JUMLAH_PRODUK_CONTOH_MAKSIMAL],
            'ProdukContoh.*' => ['array'],
            'ProdukContoh.*.Nama' => ['required', 'string', 'max:'.ValidatorTemplate::PANJANG_NAMA_PRODUK_MAKSIMAL],
            'ProdukContoh.*.Kategori' => ['nullable', 'string', 'max:60'],
            'ProdukContoh.*.Harga' => ['required', 'string', 'regex:'.ValidatorTemplate::POLA_HARGA],
            'ProdukContoh.*.KodeSatuan' => ['required', 'string', 'max:20'],
            'ProdukContoh.*.Jenis' => ['required', 'string', Rule::enum(JenisProdukContoh::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Pengaturan.PembulatanTunai.Kelipatan.*' => 'Kelipatan pembulatan bilangan bulat Rupiah, misal 100.',
            'Pengaturan.PersenBiayaLayanan.regex' => 'Service charge berupa persen, misal 5 atau 7.5.',
            'ProdukContoh.present' => 'Daftar produk contoh wajib dikirim (boleh kosong).',
            'ProdukContoh.max' => 'Produk contoh paling banyak '.ValidatorTemplate::JUMLAH_PRODUK_CONTOH_MAKSIMAL.' item.',
            'ProdukContoh.*.Nama.required' => 'Nama produk contoh wajib diisi.',
            'ProdukContoh.*.Nama.max' => 'Nama produk contoh paling panjang '.ValidatorTemplate::PANJANG_NAMA_PRODUK_MAKSIMAL.' karakter.',
            'ProdukContoh.*.Harga.required' => 'Harga produk contoh wajib diisi.',
            'ProdukContoh.*.Harga.regex' => 'Harga berupa angka Rupiah tanpa titik ribuan, misal 22000 atau 22000.50.',
            'ProdukContoh.*.KodeSatuan.required' => 'Pilih satuan produk contoh.',
            'ProdukContoh.*.Jenis.*' => 'Pilih jenis produk contoh: Stok, NonStok, atau Jasa.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilIsi(): array
    {
        $daftar = fn (string $kunci): array => array_values(array_map(
            fn (mixed $nilai) => trim((string) $nilai),
            (array) $this->input($kunci, []),
        ));

        return [
            'ModeKasir' => $daftar('ModeKasir'),
            'ModeKasirDefault' => $this->filled('ModeKasirDefault') ? $this->string('ModeKasirDefault')->toString() : null,
            'KunciFitur' => $daftar('KunciFitur'),
            'Kategori' => $daftar('Kategori'),
            'KodeSatuan' => $daftar('KodeSatuan'),
            'StasiunDapur' => $daftar('StasiunDapur'),
            'AlasanVoid' => $daftar('AlasanVoid'),
            'AlasanPenyesuaian' => $daftar('AlasanPenyesuaian'),
            'LaporanUnggulan' => $daftar('LaporanUnggulan'),
            'ProdukContoh' => array_map(function (array $produk): array {
                $kategori = is_string($produk['Kategori'] ?? null) ? trim($produk['Kategori']) : '';

                return [
                    'Nama' => trim((string) $produk['Nama']),
                    'Kategori' => $kategori === '' ? null : $kategori,
                    'Harga' => (string) $produk['Harga'],
                    'KodeSatuan' => (string) $produk['KodeSatuan'],
                    'Jenis' => (string) $produk['Jenis'],
                ];
            }, array_values(array_filter((array) $this->input('ProdukContoh', []), 'is_array'))),
            'Pengaturan' => [
                'PembulatanTunai' => [
                    'Kelipatan' => $this->integer('Pengaturan.PembulatanTunai.Kelipatan'),
                    'Arah' => $this->string('Pengaturan.PembulatanTunai.Arah')->toString(),
                ],
                'PersenBiayaLayanan' => $this->string('Pengaturan.PersenBiayaLayanan')->toString(),
                'BiayaLayananMasukDpp' => $this->boolean('Pengaturan.BiayaLayananMasukDpp'),
                'StokBolehMinus' => $this->boolean('Pengaturan.StokBolehMinus'),
                'MetodeHpp' => $this->string('Pengaturan.MetodeHpp')->toString(),
                'HargaTermasukPajak' => $this->boolean('Pengaturan.HargaTermasukPajak'),
            ],
        ];
    }
}
