<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Model\PengaturanLaundry;
use Illuminate\Support\Facades\DB;

/**
 * Simpan pengaturan laundry (§9.9): aktif di kasir, durasi reguler 1–720 jam & express 1–720 jam (express tidak lebih
 * lama dari reguler), daftar parfum (maks 20, masing-masing ≤ 50 huruf, tanpa duplikat), notifikasi WhatsApp saat siap,
 * batas hari belum diambil 1–90. Audit `laundry.pengaturan`.
 *
 * @phpstan-type Masukan array{Aktif: bool, JamReguler: int, JamExpress: int, Parfum: list<string>, NotifikasiSiap: bool, HariBelumDiambil: int}
 */
final class SimpanPengaturanLaundry
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  Masukan  $data
     */
    public function Jalankan(array $data, int $idPengguna): void
    {
        foreach (['JamReguler' => 'Durasi reguler', 'JamExpress' => 'Durasi express'] as $kunci => $label) {
            if ($data[$kunci] < 1 || $data[$kunci] > 720) {
                throw new PelanggaranAturanBisnis('PengaturanTidakValid', "{$label} 1–720 jam.", $kunci);
            }
        }

        if ($data['JamExpress'] > $data['JamReguler']) {
            throw new PelanggaranAturanBisnis('PengaturanTidakValid', 'Durasi express tidak boleh lebih lama dari reguler.', 'JamExpress');
        }

        if ($data['HariBelumDiambil'] < 1 || $data['HariBelumDiambil'] > 90) {
            throw new PelanggaranAturanBisnis('PengaturanTidakValid', 'Batas cucian belum diambil 1–90 hari.', 'HariBelumDiambil');
        }

        $parfum = [];

        foreach ($data['Parfum'] as $nama) {
            $nama = trim(preg_replace('/\s+/u', ' ', $nama) ?? '');

            if ($nama !== '' && ! in_array(mb_strtolower($nama), array_map('mb_strtolower', $parfum), true)) {
                $parfum[] = mb_substr($nama, 0, 50);
            }
        }

        if (count($parfum) > 20) {
            throw new PelanggaranAturanBisnis('PengaturanTidakValid', 'Paling banyak 20 pilihan parfum.', 'Parfum');
        }

        $data['Parfum'] = $parfum;

        DB::transaction(function () use ($data, $idPengguna): void {
            $p = PengaturanLaundry::query()->lockForUpdate()->first() ?? new PengaturanLaundry;
            $lama = [
                'Aktif' => $p->Aktif,
                'JamReguler' => $p->JamReguler,
                'JamExpress' => $p->JamExpress,
                'Parfum' => array_values($p->Parfum ?? []),
                'NotifikasiSiap' => $p->NotifikasiSiap,
                'HariBelumDiambil' => $p->HariBelumDiambil,
            ];
            $p->fill($data);
            $p->save();

            if ($lama != $data) {
                $this->audit->Catat('laundry.pengaturan', $p, $lama, $data, idPengguna: $idPengguna);
            }
        });
    }
}
