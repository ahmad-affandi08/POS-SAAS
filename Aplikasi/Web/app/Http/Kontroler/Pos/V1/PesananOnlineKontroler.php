<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Penjualan\Aksi\TautkanPenjualanPesananOnline;
use App\Domain\Penjualan\Aksi\UbahStatusPesananOnline;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Kueri\PesananOnlineOutlet;
use App\Domain\Penjualan\Model\PesananOnline;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PesananOnlineKontroler extends Kontroler
{
    public function Ambil(Request $request, PesananOnlineOutlet $kueri): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);

        return response()->json($kueri->AmbilAktif($perangkat->IdOutlet));
    }

    /** BR-17.3 (v3.33): ringkasan untuk polling kasir tiap 10 detik (`?sejak=` ISO-8601 dari `WaktuServer` sebelumnya). */
    public function Ringkas(Request $request, PesananOnlineOutlet $kueri): JsonResponse
    {
        $valid = $request->validate(['sejak' => ['nullable', 'date']]);
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);
        $sejak = is_string($valid['sejak'] ?? null) ? CarbonImmutable::parse($valid['sejak']) : null;

        return response()->json($kueri->AmbilRingkas($perangkat->IdOutlet, $sejak));
    }

    /**
     * BR-17.3 (v3.33): staf di kasir menerima, menolak, memproses, atau menandai siap pesanan online outletnya — lewat
     * aksi yang sama dengan back-office (riwayat, audit, kabar WhatsApp ke pembeli). Pelaku wajib anggota outlet ber-izin
     * `toko-online.kelola` atau `penjualan.buat` (kasir yang melayani pesanan di konter). Idempoten menurut keadaan akhir:
     * kiriman ulang untuk status yang sudah terpasang dijawab 200 tanpa perubahan.
     */
    public function UbahStatus(Request $request, string $pesananOnline, AnggotaOutlet $anggota, UbahStatusPesananOnline $ubah): JsonResponse
    {
        $valid = $request->validate([
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'Status' => ['required', Rule::enum(StatusPesananOnline::class)->only(StatusPesananOnline::PilihanStaf())],
            'Alasan' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn (): bool => in_array($request->input('Status'), ['Ditolak', 'Dibatalkan'], true))],
        ]);
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);
        $pelaku = $anggota->Cari($perangkat->IdTenant, strtoupper((string) $valid['UuidPengguna']), $perangkat->IdOutlet);

        if ($pelaku === null || (! $pelaku->CekIzin(IzinTenant::TokoOnlineKelola->value) && ! $pelaku->CekIzin(IzinTenant::PenjualanBuat->value))) {
            throw new PelanggaranAturanBisnis('TanpaIzin', 'Pengguna ini tidak boleh mengubah status pesanan online.', 'UuidPengguna', 403);
        }

        $pesanan = PesananOnline::query()->where('Uuid', strtoupper($pesananOnline))->where('IdOutlet', $perangkat->IdOutlet)->first()
            ?? throw new PelanggaranAturanBisnis('PesananTidakDitemukan', 'Pesanan online tidak ditemukan di outlet ini.', 'Umum', 404);
        $tujuan = StatusPesananOnline::from((string) $valid['Status']);

        if ($pesanan->Status !== $tujuan) {
            if (! $pesanan->Status->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakValid', "Pesanan {$pesanan->Nomor} sudah {$pesanan->Status->AmbilLabel()}.", 'Status', 409);
            }
            $ubah->Jalankan($pesanan, $tujuan, $pelaku->id, $valid['Alasan'] ?? null);
        }

        return response()->json(['Uuid' => $pesanan->Uuid, 'Status' => $pesanan->refresh()->Status->value]);
    }

    public function Tautkan(Request $request, string $pesananOnline, TautkanPenjualanPesananOnline $tautkan): JsonResponse
    {
        $valid = $request->validate(['UuidPenjualan' => ['required', 'ulid']]);
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);
        $pesanan = $tautkan->Jalankan($perangkat->IdOutlet, strtoupper($pesananOnline), strtoupper((string) $valid['UuidPenjualan']));

        return response()->json(['Uuid' => $pesanan->Uuid, 'Status' => $pesanan->Status->value]);
    }
}
