<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Katalog;

use App\Domain\Pengelola\Katalog\Data\DataPaket;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Foundation\Http\FormRequest;

final class SimpanPaketPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $aturan = [
            'Kode' => ['required', 'string', 'max:30'],
            'Nama' => ['required', 'string', 'max:100'],
            'Keterangan' => ['nullable', 'string', 'max:500'],
            'HargaNegosiasi' => ['required', 'boolean'],
            'MasaTrialHari' => ['required', 'integer', 'min:0', 'max:365'],
            'Urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'Batas' => ['array'],
            'KunciFitur' => ['present', 'array'],
            'KunciFitur.*' => ['string', 'distinct', 'exists:Fitur,Kunci'],
            'Alasan' => ['nullable', 'string', 'max:500'],
        ];

        foreach (Paket::KOLOM_BATAS as $kolom) {
            $aturan["Batas.{$kolom}"] = ['nullable', 'integer', 'min:0'];
        }

        return $aturan;
    }

    public function AmbilData(): DataPaket
    {
        $batas = [];

        foreach (Paket::KOLOM_BATAS as $kolom) {
            $nilai = $this->input("Batas.{$kolom}");
            $batas[$kolom] = $nilai === null || $nilai === '' ? null : (int) $nilai;
        }

        /** @var list<string> $kunciFitur */
        $kunciFitur = array_values(array_map('strval', $this->array('KunciFitur')));

        return new DataPaket(
            kode: $this->string('Kode')->toString(),
            nama: trim($this->string('Nama')->toString()),
            keterangan: $this->filled('Keterangan') ? trim($this->string('Keterangan')->toString()) : null,
            hargaNegosiasi: $this->boolean('HargaNegosiasi'),
            masaTrialHari: $this->integer('MasaTrialHari'),
            batas: $batas,
            kunciFitur: $kunciFitur,
            urutan: $this->integer('Urutan'),
        );
    }

    public function AmbilAlasan(): ?string
    {
        return $this->filled('Alasan') ? trim($this->string('Alasan')->toString()) : null;
    }
}
