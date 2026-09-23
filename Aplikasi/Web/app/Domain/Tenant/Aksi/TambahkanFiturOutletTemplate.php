<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\OutletFitur;
use Illuminate\Support\Facades\DB;

/**
 * F-01 (BR-01.3): modul template sektor menjadi baris `OutletFitur` aktif. Aditif: baris yang sudah ada tidak
 * dinonaktifkan atau dihapus. Kunci yang tidak ada di katalog fitur (P-04) diabaikan. Konfigurasi POS (mode kasir)
 * disimpan di `pos.retail` hanya bila belum pernah diisi. Mengunci baris Tenant sendiri (kirim ganda aman).
 */
final class TambahkanFiturOutletTemplate
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $kunciFitur
     * @param  array<string, mixed>|null  $konfigurasiPos
     * @return list<string> kunci yang ditambahkan
     */
    public function Jalankan(int $idOutlet, array $kunciFitur, ?array $konfigurasiPos): array
    {
        return DB::transaction(function () use ($idOutlet, $kunciFitur, $konfigurasiPos): array {
            // Kunci Tenant sendiri (reentran dalam satu transaksi): aman walau pemanggil belum mengunci.
            $this->penguncian->Kunci($this->konteks->Wajib());
            $dikenal = $kunciFitur === [] ? [] : array_map('strval', Fitur::query()->whereIn('Kunci', $kunciFitur)->pluck('Kunci')->all());
            $ada = OutletFitur::query()->where('IdOutlet', $idOutlet)->get()->keyBy('KunciFitur');
            $ditambahkan = [];

            foreach (array_values(array_unique($kunciFitur)) as $kunci) {
                if (! in_array($kunci, $dikenal, true) || $ada->has($kunci)) {
                    continue;
                }

                $baris = OutletFitur::query()->create([
                    'IdOutlet' => $idOutlet,
                    'KunciFitur' => $kunci,
                    'Aktif' => true,
                    'Konfigurasi' => $kunci === OutletFitur::KUNCI_POS ? $konfigurasiPos : null,
                ]);
                $ada->put($kunci, $baris);
                $ditambahkan[] = $kunci;
            }

            $pos = $ada->get(OutletFitur::KUNCI_POS);

            if ($pos instanceof OutletFitur && $pos->Konfigurasi === null && $konfigurasiPos !== null) {
                $pos->Konfigurasi = $konfigurasiPos;
                $pos->save();

                if (! in_array(OutletFitur::KUNCI_POS, $ditambahkan, true)) {
                    $this->audit->Catat('outlet.fitur.konfigurasi-template', $pos, nilaiLama: ['Konfigurasi' => null], nilaiBaru: [
                        'IdOutlet' => $idOutlet,
                        'Kunci' => OutletFitur::KUNCI_POS,
                        'Konfigurasi' => $konfigurasiPos,
                    ]);
                }
            }

            if ($ditambahkan !== []) {
                $this->audit->Catat('outlet.fitur.tambah-template', nilaiBaru: ['IdOutlet' => $idOutlet, 'Kunci' => $ditambahkan]);
            }

            return $ditambahkan;
        });
    }
}
