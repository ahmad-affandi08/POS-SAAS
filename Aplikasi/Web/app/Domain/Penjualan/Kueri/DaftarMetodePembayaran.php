<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Referensi\Kueri\ReferensiBankAktif;

/**
 * Metode pembayaran tenant aktif (F-01 langkah 5, checklist "Langkah Berikutnya"). Path gambar QRIS tidak pernah
 * dikirim; pemanggil membuat tautan unduh lewat rute yang memeriksa izin.
 */
final class DaftarMetodePembayaran
{
    public function __construct(private readonly ReferensiBankAktif $referensiBank) {}

    /**
     * @return list<array{Uuid: string, Jenis: string, LabelJenis: string, Nama: string, NamaBank: string|null, NomorRekening: string|null, NamaPemilikRekening: string|null, PersenBiaya: string, AdaGambarQris: bool, Aktif: bool, Wajib: bool}>
     */
    public function Ambil(): array
    {
        $daftar = MetodePembayaran::query()->orderBy('Urutan')->orderBy('Id')->get();
        $namaBank = $this->referensiBank->AmbilNama(array_values(array_filter($daftar->pluck('IdReferensiBank')->all(), 'is_int')));

        return array_values($daftar->map(fn (MetodePembayaran $metode): array => [
            'Uuid' => $metode->Uuid,
            'Jenis' => $metode->Jenis->value,
            'LabelJenis' => $metode->Jenis->AmbilLabel(),
            'Nama' => $metode->Nama,
            'NamaBank' => $metode->IdReferensiBank === null ? null : ($namaBank[$metode->IdReferensiBank] ?? null),
            'NomorRekening' => $metode->NomorRekening,
            'NamaPemilikRekening' => $metode->NamaPemilikRekening,
            'PersenBiaya' => $metode->PersenBiaya,
            'AdaGambarQris' => $metode->PathGambarQris !== null,
            'Aktif' => $metode->Aktif,
            'Wajib' => $metode->Jenis === JenisMetodePembayaran::Tunai,
        ])->all());
    }

    public function Cari(string $uuid): ?MetodePembayaran
    {
        return MetodePembayaran::query()->where('Uuid', $uuid)->first();
    }

    /** Checklist: metode pembayaran aktif selain Tunai yang sudah diatur. */
    public function HitungAktifSelainTunai(): int
    {
        return MetodePembayaran::query()
            ->where('Aktif', true)
            ->where('Jenis', '!=', JenisMetodePembayaran::Tunai->value)
            ->count();
    }
}
