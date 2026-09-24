<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Data\DataSaringProduk;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\StatusProduk;
use App\Domain\Katalog\Model\Kategori;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ekspor produk F-03 (E.2): `?format=xlsx|csv&kata=&saring[Kategori|Jenis|Status]=&urut=` — saringan sama persis
 * dengan halaman daftar produk. Kategori tenant lain/tidak dikenal = hasil kosong (bukan semua produk).
 */
final class EksporProdukPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:xlsx,csv'],
            'kata' => ['nullable', 'string', 'max:100'],
            'saring' => ['nullable', 'array'],
            'saring.Kategori' => ['nullable', 'string', 'max:26'],
            'saring.Jenis' => ['nullable', 'string', 'max:20'],
            'saring.Status' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function AmbilFormat(): string
    {
        return $this->query('format') === 'csv' ? 'csv' : 'xlsx';
    }

    public function AmbilSaring(): DataSaringProduk
    {
        $saring = (array) $this->input('saring', []);
        $uuidKategori = is_string($saring['Kategori'] ?? null) && $saring['Kategori'] !== '' ? $saring['Kategori'] : null;
        $idKategori = $uuidKategori === null ? null : Kategori::query()->where('Uuid', $uuidKategori)->value('Id');
        $statusTeks = is_string($saring['Status'] ?? null) ? $saring['Status'] : 'Aktif';

        return new DataSaringProduk(
            trim($this->string('kata')->toString()),
            is_int($idKategori) ? $idKategori : ($uuidKategori === null ? null : 0),
            is_string($saring['Jenis'] ?? null) ? JenisProduk::tryFrom($saring['Jenis']) : null,
            $statusTeks === 'Semua' ? null : (StatusProduk::tryFrom($statusTeks) ?? StatusProduk::Aktif),
        );
    }
}
