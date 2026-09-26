<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Aksi\AktifkanPerangkat;
use App\Domain\Organisasi\Aksi\SimpanProfilHardware;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use App\Domain\Tenant\Kueri\StatusLanggananTenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Permintaan\Pos\V1\AktivasiPerangkatPermintaan;
use App\Http\Respons\Pos\V1\PerangkatPosRespons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `POST /api/pos/v1/perangkat/aktivasi` (F-02 langkah 5, §16.3): tukar kode aktivasi → token perangkat, kode perangkat
 * untuk penomoran offline, info outlet & tenant. Token hanya dikirim sekali; aplikasi menyimpannya di secure storage.
 * `POST /api/pos/v1/perangkat/profil-hardware` (v1.96): laporan profil hardware & hasil Wizard Uji Perangkat.
 */
final class PerangkatKontroler extends Kontroler
{
    public function Aktivasi(
        AktivasiPerangkatPermintaan $permintaan,
        AktifkanPerangkat $aktifkan,
        RingkasanTenant $ringkasanTenant,
        StatusLanggananTenant $statusLangganan,
        PencatatAudit $audit,
    ): JsonResponse {
        $audit->AturKonteks(null, $permintaan->ip(), $permintaan->userAgent());
        $hasil = $aktifkan->Jalankan($permintaan->AmbilData());
        $idTenant = $hasil['Perangkat']->IdTenant;
        $status = $statusLangganan->Ambil($idTenant);

        return response()->json([
            'TokenPerangkat' => $hasil['TokenPerangkat'],
            // F-06 (§25.2 no. 3): kunci pembungkus verifier PIN offline, dikirim sekali; aplikasi menyimpannya di
            // secure storage (Keystore/Keychain/DPAPI). Tambahan kontrak yang kompatibel mundur.
            'KunciPinOffline' => $hasil['Perangkat']->KunciPinOffline,
            'Perangkat' => PerangkatPosRespons::Perangkat($hasil['Perangkat']),
            'Outlet' => PerangkatPosRespons::Outlet($hasil['Outlet']),
            'Tenant' => PerangkatPosRespons::Tenant($ringkasanTenant->Ambil([$idTenant])[0] ?? null),
            'Langganan' => PerangkatPosRespons::Langganan($status, $statusLangganan->CekBolehBertransaksiPos($status)),
        ], 201);
    }

    public function SimpanProfilHardware(Request $permintaan, SimpanProfilHardware $simpan): JsonResponse
    {
        $hasilUji = ['nullable', Rule::in(['Lolos', 'Gagal', 'Dilewati'])];
        $data = $permintaan->validate([
            'Produsen' => ['nullable', 'string', 'max:100'],
            'Model' => ['nullable', 'string', 'max:100'],
            'Sistem' => ['nullable', 'string', 'max:100'],
            'Adaptor' => ['nullable', 'string', 'max:30'],
            'Printer' => ['nullable', 'array:Jenis,Nama,Lebar'],
            'Printer.Jenis' => ['nullable', 'string', 'max:30'],
            'Printer.Nama' => ['nullable', 'string', 'max:150'],
            'Printer.Lebar' => ['nullable', 'string', 'max:10'],
            'Uji' => ['nullable', 'array:Cetak,Potong,Laci,Pemindai'],
            'Uji.Cetak' => $hasilUji,
            'Uji.Potong' => $hasilUji,
            'Uji.Laci' => $hasilUji,
            'Uji.Pemindai' => $hasilUji,
            'DiujiPada' => ['nullable', 'date'],
        ]);
        $simpan->Jalankan(AutentikasiPerangkat::AmbilPerangkat($permintaan), $data);

        return response()->json(['Tersimpan' => true]);
    }
}
