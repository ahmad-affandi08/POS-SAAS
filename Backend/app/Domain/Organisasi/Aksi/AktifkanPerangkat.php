<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Data\DataAktivasiPerangkat;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\KodeAktivasi;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Tenant\Kueri\StatusLanggananTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-02 langkah 5: aplikasi POS menukar kode aktivasi dengan token perangkat.
 *
 * - Kode salah, kedaluwarsa, sudah dipakai, atau dibatalkan dijawab dengan galat yang sama (`KodeAktivasiTidakBerlaku`)
 *   agar tidak membocorkan kode mana yang pernah ada. Percobaan dibatasi per IP di rute.
 * - Token = `{IdTenant}|{rahasia}`; rahasia 64 byte acak, yang disimpan hanya SHA-256-nya di `Perangkat.HashToken`.
 *   Aktivasi ulang (kode baru untuk perangkat aktif) mengganti token sehingga instalasi lama keluar.
 * - Tenant yang langganannya Ditangguhkan/Berhenti tidak bisa mengaktifkan perangkat (POS terkunci, F-00).
 *
 * @phpstan-type HasilAktivasi array{Perangkat: Perangkat, Outlet: Outlet, TokenPerangkat: string}
 */
final class AktifkanPerangkat
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly StatusLanggananTenant $statusLangganan,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return HasilAktivasi
     */
    public function Jalankan(DataAktivasiPerangkat $data): array
    {
        return DB::transaction(function () use ($data): array {
            $kode = KodeAktivasi::query()->where('HashKode', KodeAktivasi::BuatHashKode($data->kode))->lockForUpdate()->first();

            if ($kode === null || ! $kode->CekBerlaku()) {
                throw new PelanggaranAturanBisnis('KodeAktivasiTidakBerlaku', 'Kode aktivasi salah, kedaluwarsa, atau sudah dipakai. Minta kode baru di back-office menu Perangkat.', 'Kode');
            }

            $this->konteks->Atur($kode->IdTenant);
            $perangkat = Perangkat::query()->lockForUpdate()->findOrFail($kode->IdPerangkat);
            $outlet = Outlet::query()->findOrFail($perangkat->IdOutlet);

            if ($perangkat->CekDicabut()) {
                throw new PelanggaranAturanBisnis('PerangkatDicabut', 'Perangkat ini sudah dicabut dari back-office.', 'Kode', 403);
            }

            if ($outlet->Status !== StatusOrganisasi::Aktif) {
                throw new PelanggaranAturanBisnis('OutletDiarsipkan', "Outlet {$outlet->Nama} sudah diarsipkan. Pulihkan outlet dulu di back-office.", 'Kode', 403);
            }

            if (! $this->statusLangganan->CekBolehBertransaksiPos($this->statusLangganan->Ambil($kode->IdTenant))) {
                throw new PelanggaranAturanBisnis('LanggananTidakAktif', 'Langganan usaha ini sedang ditangguhkan, jadi aplikasi kasir terkunci. Pemilik bisa membayar tagihan atau pindah ke paket Gratis di menu Langganan.', 'Kode', 403);
            }

            $aktivasiUlang = $perangkat->DiaktifkanPada !== null;
            $rahasia = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $perangkat->fill([
                'HashToken' => Perangkat::BuatHashToken($rahasia),
                'DiaktifkanPada' => now(),
                'TerakhirAktifPada' => now(),
                'Platform' => $data->platform,
                'VersiAplikasi' => $data->versiAplikasi,
                'VersiOs' => $data->versiOs,
                'VersiSkemaSinkron' => $data->versiSkemaSinkron,
            ])->save();

            $kode->DipakaiPada = now();
            $kode->save();

            $this->audit->AturPerangkat($perangkat->Id);
            $this->audit->Catat('perangkat.aktivasi', $perangkat, nilaiBaru: [
                'Platform' => $data->platform->value,
                'VersiAplikasi' => $data->versiAplikasi,
                'VersiOs' => $data->versiOs,
                'AktivasiUlang' => $aktivasiUlang,
            ]);

            return ['Perangkat' => $perangkat, 'Outlet' => $outlet, 'TokenPerangkat' => "{$kode->IdTenant}|{$rahasia}"];
        });
    }
}
