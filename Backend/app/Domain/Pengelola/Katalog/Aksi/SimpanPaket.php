<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Katalog\Data\DataPaket;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah isi paket: nama, masa trial, batas, dan fitur (P-04). Harga tidak di sini (HargaPaket).
 * BR-P04.6: paket yang sudah Aktif/Diarsipkan hanya boleh diubah pemegang izin persetujuan dengan alasan wajib,
 * karena perubahan langsung berdampak ke tenant.
 */
final class SimpanPaket
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataPaket $data, ?Paket $paket = null, ?string $alasan = null): Paket
    {
        return DB::transaction(function () use ($pelaku, $data, $paket, $alasan): Paket {
            if ($paket !== null) {
                $paket = Paket::query()->lockForUpdate()->findOrFail($paket->Id);

                if ($paket->Kode !== $data->kode) {
                    throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode paket tidak bisa diubah.', 'Kode');
                }

                if ($paket->Status !== StatusPaket::Draf) {
                    if (! $pelaku->PunyaIzin(IzinPengelola::KatalogPaketSetujui)) {
                        throw new PelanggaranAturanBisnis('BR-P04.6', 'Paket yang sudah aktif hanya bisa diubah Super Admin.');
                    }

                    if ($alasan === null || trim($alasan) === '') {
                        throw new PelanggaranAturanBisnis('BR-P04.6', 'Tulis alasan perubahan: paket aktif langsung berdampak ke tenant.', 'Alasan');
                    }
                }
            } elseif (Paket::query()->where('Kode', $data->kode)->exists()) {
                throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode paket {$data->kode} sudah ada.", 'Kode');
            }

            if (preg_match('/^[A-Z0-9_]{2,30}$/', $data->kode) !== 1) {
                throw new PelanggaranAturanBisnis('KodeTidakValid', 'Kode paket huruf besar/angka/garis bawah, misal PRO.', 'Kode');
            }

            $kunciFitur = array_values(array_unique($data->kunciFitur));
            $fiturAda = Fitur::query()->whereIn('Kunci', $kunciFitur)->count();

            if ($fiturAda !== count($kunciFitur)) {
                throw new PelanggaranAturanBisnis('FiturTidakDikenal', 'Ada fitur yang tidak terdaftar di katalog.', 'KunciFitur');
            }

            foreach ($data->batas as $kolom => $nilai) {
                if (! in_array($kolom, Paket::KOLOM_BATAS, true) || ($nilai !== null && $nilai < 0)) {
                    throw new PelanggaranAturanBisnis('BatasTidakValid', 'Batas harus angka 0 atau lebih, atau kosong untuk tak terbatas.', $kolom);
                }
            }

            $nilaiLama = $paket === null ? null : self::AmbilNilai($paket);
            $paket ??= new Paket(['Status' => StatusPaket::Draf]);
            $paket->fill([
                'Kode' => $data->kode,
                'Nama' => $data->nama,
                'Keterangan' => $data->keterangan,
                'HargaNegosiasi' => $data->hargaNegosiasi,
                'MasaTrialHari' => max(0, $data->masaTrialHari),
                'Urutan' => $data->urutan,
                ...$data->batas,
            ])->save();

            $paket->Fitur()->whereNotIn('KunciFitur', $kunciFitur)->delete();

            foreach ($kunciFitur as $kunci) {
                $paket->Fitur()->firstOrCreate(['KunciFitur' => $kunci]);
            }

            $paket->unsetRelation('Fitur');

            $this->audit->Catat(
                $nilaiLama === null ? 'katalog.paket.buat' : 'katalog.paket.ubah',
                $paket,
                nilaiLama: $nilaiLama,
                nilaiBaru: self::AmbilNilai($paket),
                alasan: $alasan,
                idPelaku: $pelaku->Id,
            );

            return $paket;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function AmbilNilai(Paket $paket): array
    {
        return [
            'Nama' => $paket->Nama,
            'Status' => $paket->Status->value,
            'HargaNegosiasi' => $paket->HargaNegosiasi,
            'MasaTrialHari' => $paket->MasaTrialHari,
            'Batas' => $paket->AmbilBatas(),
            'Fitur' => $paket->AmbilKunciFitur(),
        ];
    }
}
