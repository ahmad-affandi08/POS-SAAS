<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembayaran;

use App\Domain\Integrasi\Data\DataGerbangPembayaranTenant;
use App\Domain\Integrasi\Enum\LingkunganGerbang;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Gerbang pembayaran tenant (v2.06): aturan bidang mengikuti penyedia yang dipilih. Kredensial boleh kosong saat
 * menyunting (kosong = pertahankan); kewajiban kredensial saat membuat/ganti penyedia dinilai di Aksi.
 */
final class SimpanGerbangPembayaranPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $aturan = [
            'Penyedia' => ['required', 'string', Rule::enum(PenyediaGerbang::class)],
            'Lingkungan' => ['required', 'string', Rule::enum(LingkunganGerbang::class)],
            'Pengaturan' => ['present', 'array'],
            'Kredensial' => ['present', 'array'],
        ];

        foreach ($this->AmbilPenyedia()?->AmbilBidangPengaturan() ?? [] as $bidang) {
            $aturan["Pengaturan.{$bidang['Kunci']}"] = ! $bidang['Wajib'] ? ['nullable', 'string', 'max:255'] : match ($bidang['Jenis']) {
                'Pilihan' => ['required', 'string', Rule::in($bidang['Opsi'] ?? [])],
                default => ['required', 'string', 'max:255'],
            };
        }

        foreach ($this->AmbilPenyedia()?->AmbilBidangKredensial() ?? [] as $bidang) {
            $aturan["Kredensial.{$bidang['Kunci']}"] = ['nullable', 'string', 'max:2000'];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $nama = ['Penyedia' => 'penyedia', 'Lingkungan' => 'lingkungan'];

        foreach ($this->AmbilPenyedia()?->AmbilBidangPengaturan() ?? [] as $bidang) {
            $nama["Pengaturan.{$bidang['Kunci']}"] = $bidang['Label'];
        }

        return $nama;
    }

    public function AmbilData(): DataGerbangPembayaranTenant
    {
        $penyedia = PenyediaGerbang::from($this->string('Penyedia')->toString());
        $pengaturan = [];
        $kredensial = [];

        foreach ($penyedia->AmbilBidangPengaturan() as $bidang) {
            $pengaturan[$bidang['Kunci']] = trim($this->string("Pengaturan.{$bidang['Kunci']}")->toString());
        }

        foreach ($penyedia->AmbilBidangKredensial() as $bidang) {
            $nilai = trim($this->string("Kredensial.{$bidang['Kunci']}")->toString());

            if ($nilai !== '') {
                $kredensial[$bidang['Kunci']] = $nilai;
            }
        }

        return new DataGerbangPembayaranTenant(
            penyedia: $penyedia,
            lingkungan: LingkunganGerbang::from($this->string('Lingkungan')->toString()),
            pengaturan: $pengaturan,
            kredensial: $kredensial,
        );
    }

    private function AmbilPenyedia(): ?PenyediaGerbang
    {
        return PenyediaGerbang::tryFrom($this->string('Penyedia')->toString());
    }
}
