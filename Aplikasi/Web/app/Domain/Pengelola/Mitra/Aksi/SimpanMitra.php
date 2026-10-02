<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Mitra\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\Mitra;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Menambah atau mengubah mitra (P-12 langkah 1–2). Kode tidak bisa diubah (sudah tersebar di tautan mitra). Nomor HP,
 * NPWP, dan nomor rekening dirahasiakan: bidang kosong saat mengubah = tetap; nilainya tidak pernah masuk log audit,
 * hanya tanda bahwa ia berubah.
 */
final class SimpanMitra
{
    private const RAHASIA = ['NoHp', 'Npwp', 'NomorRekening'];

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /** @param array<string, mixed> $data hasil validasi kontroler */
    public function Jalankan(PenggunaPengelola $pelaku, array $data, ?Mitra $mitra = null): Mitra
    {
        $kode = strtoupper(trim((string) ($data['Kode'] ?? '')));

        if ($mitra !== null && $mitra->Kode !== $kode) {
            throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode mitra tidak bisa diubah karena sudah dipakai di tautan.', 'Kode');
        }

        if (preg_match('/^[A-Z0-9-]{3,20}$/', $kode) !== 1) {
            throw new PelanggaranAturanBisnis('KodeTidakValid', 'Kode mitra huruf besar/angka/tanda hubung, 3–20 karakter.', 'Kode');
        }

        $persen = BigDecimal::of((string) $data['PersenKomisi']);

        if ($persen->isNegative() || $persen->isGreaterThan(100) || $persen->getScale() > 2) {
            throw new PelanggaranAturanBisnis('PersenTidakValid', 'Persen komisi 0–100 dengan paling banyak dua angka desimal.', 'PersenKomisi');
        }

        try {
            return DB::transaction(function () use ($pelaku, $data, $mitra, $kode, $persen): Mitra {
                $baris = $mitra ?? new Mitra(['Kode' => $kode, 'DibuatOleh' => $pelaku->Id]);
                $lama = $mitra === null ? null : self::AmbilNilai($mitra);
                $baris->fill([
                    'Nama' => trim((string) $data['Nama']),
                    'Jenis' => $data['Jenis'],
                    'Status' => $data['Status'],
                    'Email' => self::Teks($data['Email'] ?? null),
                    'NamaBank' => self::Teks($data['NamaBank'] ?? null),
                    'NamaPemilikRekening' => self::Teks($data['NamaPemilikRekening'] ?? null),
                    'PersenKomisi' => (string) $persen->toScale(2),
                    'KomisiBerulang' => (bool) $data['KomisiBerulang'],
                    'Catatan' => self::Teks($data['Catatan'] ?? null),
                ]);
                $rahasiaBerubah = [];

                foreach (self::RAHASIA as $kolom) {
                    $nilai = self::Teks($data[$kolom] ?? null);

                    if ($nilai !== null) {
                        $baris->setAttribute($kolom, $nilai);
                        $rahasiaBerubah[] = $kolom;
                    }
                }

                $baris->save();
                $this->audit->Catat(
                    $mitra === null ? 'mitra.buat' : 'mitra.ubah',
                    $baris,
                    nilaiLama: $lama,
                    nilaiBaru: [...self::AmbilNilai($baris), 'RahasiaDiubah' => $rahasiaBerubah],
                    idPelaku: $pelaku->Id,
                );

                return $baris;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode mitra {$kode} sudah dipakai.", 'Kode');
        }
    }

    /** @return array<string, mixed> */
    private static function AmbilNilai(Mitra $mitra): array
    {
        return [
            'Kode' => $mitra->Kode, 'Nama' => $mitra->Nama, 'Jenis' => $mitra->Jenis->value, 'Status' => $mitra->Status->value,
            'PersenKomisi' => $mitra->PersenKomisi, 'KomisiBerulang' => $mitra->KomisiBerulang,
        ];
    }

    private static function Teks(mixed $nilai): ?string
    {
        $teks = is_string($nilai) ? trim($nilai) : '';

        return $teks === '' ? null : $teks;
    }
}
