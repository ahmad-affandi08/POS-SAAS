<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pelanggan;

use App\Domain\Pelanggan\Aksi\SimpanKampanyePesan;
use App\Domain\Pelanggan\Data\DataKampanyePesan;
use App\Domain\Pelanggan\Enum\KanalKampanye;
use App\Domain\Pelanggan\Enum\SegmenRfm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Isian kampanye pesan CRM-07 (draf & pratinjau penerima). */
final class SimpanKampanyePesanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:120'],
            'Kanal' => ['required', Rule::enum(KanalKampanye::class)],
            'Judul' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn (): bool => $this->input('Kanal') === KanalKampanye::Email->value)],
            'Isi' => ['required', 'string', 'min:10', 'max:'.SimpanKampanyePesan::MAKS_ISI],
            ...self::AturanSegmen(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function AturanSegmen(): array
    {
        return [
            'Segmen' => ['nullable', 'array'],
            'Segmen.Rfm' => ['nullable', 'array', 'max:7'],
            'Segmen.Rfm.*' => ['string', Rule::enum(SegmenRfm::class)],
            'Segmen.UuidTier' => ['nullable', 'array', 'max:50'],
            'Segmen.UuidTier.*' => ['string', 'ulid'],
            'Segmen.Tag' => ['nullable', 'array', 'max:50'],
            'Segmen.Tag.*' => ['string', 'max:40'],
            'Segmen.UlangTahunBulanIni' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Nama' => 'nama kampanye', 'Kanal' => 'kanal', 'Judul' => 'judul email', 'Isi' => 'isi pesan'];
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array{Rfm?: list<string>, UuidTier?: list<string>, Tag?: list<string>, UlangTahunBulanIni?: bool}
     */
    public static function AmbilSegmen(array $nilai): array
    {
        $segmen = is_array($nilai['Segmen'] ?? null) ? $nilai['Segmen'] : [];
        $daftar = static fn (string $kunci): array => array_values(array_unique(array_filter(
            array_map(fn (mixed $v): string => is_string($v) ? trim($v) : '', is_array($segmen[$kunci] ?? null) ? $segmen[$kunci] : []),
            fn (string $v): bool => $v !== '',
        )));

        return [
            'Rfm' => $daftar('Rfm'),
            'UuidTier' => array_map('strtoupper', $daftar('UuidTier')),
            'Tag' => $daftar('Tag'),
            'UlangTahunBulanIni' => filter_var($segmen['UlangTahunBulanIni'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    public function AmbilData(int $idPengguna): DataKampanyePesan
    {
        $valid = $this->validated();

        return new DataKampanyePesan(
            (string) $valid['Nama'],
            KanalKampanye::from((string) $valid['Kanal']),
            isset($valid['Judul']) ? (string) $valid['Judul'] : null,
            (string) $valid['Isi'],
            self::AmbilSegmen($valid),
            $idPengguna,
        );
    }
}
