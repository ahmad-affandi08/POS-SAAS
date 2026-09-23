<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pengelola\Katalog\Data\DataAddon;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Addon;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Paket;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Membuat atau mengubah add-on (P-04): fitur dan/atau tambahan batas. Tidak dihapus; yang tidak dijual lagi
 * diarsipkan. Harga add-on berlaku untuk tagihan berikutnya karena tagihan menyimpan salinan harga (P-08).
 */
final class SimpanAddon
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataAddon $data, ?Addon $addon = null): Addon
    {
        return DB::transaction(function () use ($pelaku, $data, $addon): Addon {
            if ($addon !== null && $addon->Kode !== $data->kode) {
                throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode add-on tidak bisa diubah.', 'Kode');
            }

            if ($addon === null && Addon::query()->where('Kode', $data->kode)->exists()) {
                throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode add-on {$data->kode} sudah ada.", 'Kode');
            }

            if ($data->status === StatusPaket::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Status add-on hanya Aktif atau Diarsipkan.', 'Status');
            }

            try {
                $harga = Uang::Dari($data->hargaBulanan);
            } catch (InvalidArgumentException|MathException) {
                throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga berupa angka Rupiah, maksimal 2 desimal.', 'HargaBulanan');
            }

            if ($harga->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga tidak boleh negatif.', 'HargaBulanan');
            }

            if ($data->kunciFitur !== null && ! Fitur::query()->where('Kunci', $data->kunciFitur)->exists()) {
                throw new PelanggaranAturanBisnis('FiturTidakDikenal', 'Fitur tidak terdaftar di katalog.', 'KunciFitur');
            }

            foreach ($data->tambahanBatas as $kolom => $nilai) {
                if (! in_array($kolom, Paket::KOLOM_BATAS, true) || $nilai < 1) {
                    throw new PelanggaranAturanBisnis('BatasTidakValid', 'Tambahan batas harus kolom batas paket dengan nilai minimal 1.', 'TambahanBatas');
                }
            }

            if ($data->kunciFitur === null && $data->tambahanBatas === []) {
                throw new PelanggaranAturanBisnis('AddonKosong', 'Add-on harus memberi fitur, tambahan batas, atau keduanya.', 'KunciFitur');
            }

            $nilaiLama = $addon?->only(['Nama', 'HargaBulanan', 'KunciFitur', 'TambahanBatas']);
            $statusLama = $addon?->Status->value;
            $addon ??= new Addon;
            $addon->fill([
                'Kode' => $data->kode,
                'Nama' => $data->nama,
                'HargaBulanan' => $harga->KeString(),
                'KunciFitur' => $data->kunciFitur,
                'TambahanBatas' => $data->tambahanBatas === [] ? null : $data->tambahanBatas,
                'Status' => $data->status,
            ])->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'katalog.addon.buat' : 'katalog.addon.ubah',
                $addon,
                nilaiLama: $nilaiLama === null ? null : [...$nilaiLama, 'Status' => $statusLama],
                nilaiBaru: [...$addon->only(['Nama', 'HargaBulanan', 'KunciFitur', 'TambahanBatas']), 'Status' => $addon->Status->value],
                idPelaku: $pelaku->Id,
            );

            return $addon;
        });
    }
}
