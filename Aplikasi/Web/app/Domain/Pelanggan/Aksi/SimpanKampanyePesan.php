<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Data\DataKampanyePesan;
use App\Domain\Pelanggan\Enum\StatusKampanye;
use App\Domain\Pelanggan\Model\KampanyePesan;
use Illuminate\Support\Facades\DB;

/**
 * CRM-07: buat atau ubah draf kampanye pesan. Hanya `Draf` yang bisa diubah. Hanya kanal WhatsApp (D-33). Isi 10–1.000 huruf,
 * boleh memuat `{nama}` (nama pelanggan) dan `{toko}` (nama usaha). Audit `kampanye-pesan.simpan` (tanpa isi pesan).
 */
final class SimpanKampanyePesan
{
    public const MAKS_ISI = 1000;

    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(DataKampanyePesan $data, ?KampanyePesan $kampanye = null): KampanyePesan
    {
        $isi = trim($data->isi);

        if (mb_strlen($isi) < 10 || mb_strlen($isi) > self::MAKS_ISI) {
            throw new PelanggaranAturanBisnis('IsiTidakValid', 'Isi pesan 10 sampai '.self::MAKS_ISI.' huruf.', 'Isi');
        }

        if (! $data->kanal->CekTersedia()) {
            throw new PelanggaranAturanBisnis('KanalTidakTersedia', 'Kampanye lewat email tidak tersedia lagi. Pilih WhatsApp.', 'Kanal');
        }

        return DB::transaction(function () use ($data, $kampanye, $isi): KampanyePesan {
            if ($kampanye !== null) {
                $kampanye = KampanyePesan::query()->whereKey($kampanye->Id)->lockForUpdate()->firstOrFail();

                if ($kampanye->Status !== StatusKampanye::Draf) {
                    throw new PelanggaranAturanBisnis('BukanDraf', 'Kampanye yang sudah dijadwalkan atau dikirim tidak bisa diubah.', 'Umum', 409);
                }
            }

            $kampanye ??= new KampanyePesan(['DibuatOleh' => $data->idPengguna]);
            $kampanye->fill([
                'Nama' => mb_substr(trim($data->nama), 0, 120),
                'Kanal' => $data->kanal,
                'Judul' => null,
                'Isi' => $isi,
                'Segmen' => $data->segmen,
            ])->save();

            $this->audit->Catat('kampanye-pesan.simpan', $kampanye, nilaiBaru: [
                'Nama' => $kampanye->Nama,
                'Kanal' => $kampanye->Kanal->value,
                'Segmen' => $kampanye->Segmen,
            ], idPengguna: $data->idPengguna);

            return $kampanye;
        });
    }
}
