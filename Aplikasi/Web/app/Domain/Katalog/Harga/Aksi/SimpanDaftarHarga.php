<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Data\DataDaftarHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah pengaturan daftar harga (lapis 3 price engine F-03).
 * - Nama wajib, unik per tenant tanpa beda huruf besar/kecil (`DaftarHargaGanda`).
 * - `SelesaiPada > MulaiPada` bila keduanya diisi (`RentangWaktuTidakValid`).
 * - Pelaku yang dibatasi outlet (`$idOutletBoleh` ≠ null) hanya boleh membuat/mengubah daftar khusus outlet yang
 *   boleh diaksesnya: daftar semua outlet atau outlet lain ditolak (`OutletDiLuarAkses`), termasuk daftar lamanya.
 * - `IdOutlet` disimpan urut & unik; daftar kosong = semua outlet (null). Audit `daftar-harga.buat` / `.ubah`.
 *
 * Urutan kunci: Tenant → baris daftar harga.
 */
final class SimpanDaftarHarga
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh  null = pelaku boleh semua outlet
     */
    public function Jalankan(?DaftarHarga $daftar, DataDaftarHarga $data, ?array $idOutletBoleh): DaftarHarga
    {
        $idTenant = $this->konteks->Wajib();
        $nama = trim($data->nama);
        $tier = $data->tierPelanggan === null ? null : trim($data->tierPelanggan);
        $idOutlet = $data->idOutlet === null || $data->idOutlet === [] ? null : array_values(array_unique($data->idOutlet));

        if ($idOutlet !== null) {
            sort($idOutlet);
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw new PelanggaranAturanBisnis('NamaTidakValid', 'Nama daftar harga wajib diisi, maksimal 100 karakter.', 'Nama');
        }

        if ($data->mulaiPada !== null && $data->selesaiPada !== null && $data->selesaiPada->lessThanOrEqualTo($data->mulaiPada)) {
            throw new PelanggaranAturanBisnis('RentangWaktuTidakValid', 'Waktu selesai harus setelah waktu mulai.', 'SelesaiPada');
        }

        self::PastikanAksesOutlet($idOutlet, $idOutletBoleh);

        return DB::transaction(function () use ($idTenant, $daftar, $data, $nama, $tier, $idOutlet, $idOutletBoleh): DaftarHarga {
            $this->penguncian->Kunci($idTenant);

            if ($daftar !== null) {
                $daftar = DaftarHarga::query()->whereKey($daftar->Id)->lockForUpdate()->firstOrFail();
                self::PastikanAksesOutlet($daftar->IdOutlet, $idOutletBoleh);
            }

            $ganda = DaftarHarga::query()
                ->whereRaw('LOWER(Nama) = ?', [mb_strtolower($nama)])
                ->when($daftar !== null, fn ($kueri) => $kueri->whereKeyNot($daftar?->Id))
                ->exists();

            if ($ganda) {
                throw new PelanggaranAturanBisnis('DaftarHargaGanda', 'Nama daftar harga ini sudah dipakai.', 'Nama');
            }

            $nilai = [
                'Nama' => $nama,
                'IdOutlet' => $idOutlet,
                'Kanal' => $data->kanal,
                'TierPelanggan' => $tier === '' ? null : $tier,
                'MulaiPada' => $data->mulaiPada?->utc(),
                'SelesaiPada' => $data->selesaiPada?->utc(),
                'Prioritas' => $data->prioritas,
            ];

            if ($daftar === null) {
                $daftar = DaftarHarga::query()->create($nilai);
                $this->audit->Catat('daftar-harga.buat', $daftar, nilaiBaru: self::Ringkas($daftar));

                return $daftar;
            }

            $nilaiLama = self::Ringkas($daftar);
            $daftar->fill($nilai);

            if ($daftar->isDirty()) {
                $daftar->save();
                $this->audit->Catat('daftar-harga.ubah', $daftar, $nilaiLama, self::Ringkas($daftar));
            }

            return $daftar;
        });
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @param  list<int>|null  $idOutletBoleh
     */
    public static function PastikanAksesOutlet(?array $idOutlet, ?array $idOutletBoleh): void
    {
        if ($idOutletBoleh === null) {
            return;
        }

        if ($idOutlet === null || array_diff($idOutlet, $idOutletBoleh) !== []) {
            throw new PelanggaranAturanBisnis(
                'OutletDiLuarAkses',
                'Anda hanya boleh mengatur daftar harga untuk outlet yang Anda kelola. Pilih outlet Anda.',
                'UuidOutlet',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function Ringkas(DaftarHarga $daftar): array
    {
        return [
            'Nama' => $daftar->Nama,
            'IdOutlet' => $daftar->IdOutlet,
            'Kanal' => $daftar->Kanal?->value,
            'TierPelanggan' => $daftar->TierPelanggan,
            'MulaiPada' => $daftar->MulaiPada?->utc()->toIso8601ZuluString(),
            'SelesaiPada' => $daftar->SelesaiPada?->utc()->toIso8601ZuluString(),
            'Prioritas' => $daftar->Prioritas,
        ];
    }
}
