<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\LayananReservasi;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Pemenuhan\Aksi\BuatReservasi;
use App\Domain\Pemenuhan\Aksi\UbahStatusReservasi;
use App\Domain\Pemenuhan\Data\DataReservasi;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Enum\SumberReservasi;
use App\Domain\Pemenuhan\Kueri\KalenderReservasiPos;
use App\Domain\Pemenuhan\Kueri\PengaturanReservasiTenant;
use App\Domain\Pemenuhan\Kueri\ReservasiPos;
use App\Domain\Pemenuhan\Kueri\SlotReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-07 mode service bagian 2 (perlu online): `GET /api/pos/v1/reservasi?tanggal=` antrian reservasi outlet perangkat
 * (bawaan hari ini), `POST /api/pos/v1/reservasi/{uuid}/hadir` `{UuidPengguna}` check-in pelanggan (kasir ber-izin
 * `penjualan.buat` atau `reservasi.kelola`; idempoten bila sudah Hadir). Pembayaran lewat `Penjualan.Buat` dengan
 * `UuidReservasi` menyelesaikan reservasi.
 *
 * K-20: `GET /api/pos/v1/reservasi/kalender?tanggal=` layanan, staf berjadwal, & reservasi satu tanggal;
 * `GET /api/pos/v1/reservasi/slot?UuidLayanan=&Tanggal=&UuidStaf=` jam kosong; `POST /api/pos/v1/reservasi`
 * `{UuidPengguna, UuidLayanan, Tanggal, Jam, UuidStaf?, NamaPelanggan, NoHp, Catatan?}` membuat booking (sumber Pos)
 * lewat `BuatReservasi` (kunci per staf, slot dihitung ulang, bentrok = 409 `SlotTidakTersedia`).
 */
final class ReservasiKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, ReservasiPos $kueri): JsonResponse
    {
        $valid = $permintaan->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['Reservasi' => $kueri->Ambil(AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet, isset($valid['tanggal']) ? (string) $valid['tanggal'] : null)]);
    }

    public function Kalender(Request $permintaan, KalenderReservasiPos $kalender, ZonaWaktuOutlet $zona): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $tanggal = isset($valid['tanggal']) ? (string) $valid['tanggal'] : CarbonImmutable::now($zona->Ambil($perangkat->IdOutlet))->toDateString();

        return response()->json($kalender->Ambil($perangkat->IdOutlet, $tanggal));
    }

    public function Slot(Request $permintaan, SlotReservasi $slot, LayananReservasi $layanan, JadwalStafReservasi $staf, PengaturanReservasiTenant $pengaturan): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate([
            'UuidLayanan' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'UuidStaf' => ['nullable', 'string', 'ulid'],
        ]);
        $l = $layanan->Cari(strtoupper((string) $valid['UuidLayanan']));
        abort_if($l === null, 404);
        $idStaf = isset($valid['UuidStaf']) ? ($staf->CariStaf(strtoupper((string) $valid['UuidStaf']))['Id'] ?? 0) : null;

        return response()->json(['Slot' => array_map(
            fn (array $s): array => ['Jam' => $s['Jam'], 'Staf' => array_map(fn (array $k): array => ['Uuid' => $k['Uuid'], 'Nama' => $k['Nama']], $s['Staf'])],
            $slot->Hitung($perangkat->IdOutlet, (string) $valid['Tanggal'], $l['DurasiMenit'], $idStaf, $pengaturan->Ambil(), CarbonImmutable::now()->subMinutes(5)),
        )]);
    }

    public function Buat(Request $permintaan, AnggotaOutlet $anggota, BuatReservasi $buat, ReservasiPos $kueri): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate([
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'UuidLayanan' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Jam' => ['required', 'regex:/^([01]\\d|2[0-3]):[0-5]\\d$/'],
            'UuidStaf' => ['nullable', 'string', 'ulid'],
            'NamaPelanggan' => ['required', 'string', 'max:100'],
            'NoHp' => ['required', 'string', 'max:20'],
            'Catatan' => ['nullable', 'string', 'max:255'],
        ]);
        $pelaku = $this->CariPelaku($perangkat, (string) $valid['UuidPengguna'], $anggota, 'Pengguna ini tidak boleh mencatat reservasi.');
        $r = $buat->Jalankan($perangkat->IdTenant, new DataReservasi(
            idOutlet: $perangkat->IdOutlet,
            uuidLayanan: strtoupper((string) $valid['UuidLayanan']),
            tanggal: (string) $valid['Tanggal'],
            jam: (string) $valid['Jam'],
            uuidStaf: isset($valid['UuidStaf']) ? strtoupper((string) $valid['UuidStaf']) : null,
            namaPelanggan: trim((string) $valid['NamaPelanggan']),
            noHp: (string) $valid['NoHp'],
            catatan: isset($valid['Catatan']) && trim((string) $valid['Catatan']) !== '' ? trim((string) $valid['Catatan']) : null,
            sumber: SumberReservasi::Pos,
            idPengguna: $pelaku->id,
        ));

        return response()->json(['Reservasi' => collect($kueri->Ambil($perangkat->IdOutlet, (string) $valid['Tanggal']))->firstWhere('Uuid', $r->Uuid)], 201);
    }

    public function Hadir(Request $permintaan, string $reservasi, AnggotaOutlet $anggota, UbahStatusReservasi $ubah, ReservasiPos $kueri, ZonaWaktuOutlet $zona): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate(['UuidPengguna' => ['required', 'string', 'ulid']]);
        $pelaku = $this->CariPelaku($perangkat, (string) $valid['UuidPengguna'], $anggota, 'Pengguna ini tidak boleh menerima pelanggan reservasi.');

        $r = Reservasi::query()->where('Uuid', strtoupper($reservasi))->where('IdOutlet', $perangkat->IdOutlet)->first();
        abort_if($r === null, 404);

        if ($r->Status !== StatusReservasi::Hadir) {
            $ubah->Jalankan($r, StatusReservasi::Hadir, $pelaku->id);
        }

        $baris = collect($kueri->Ambil($perangkat->IdOutlet, $r->MulaiPada->copy()->setTimezone($zona->Ambil($perangkat->IdOutlet))->toDateString()))->firstWhere('Uuid', $r->Uuid);

        return response()->json(['Reservasi' => $baris]);
    }

    /** Kasir ber-izin `penjualan.buat` atau `reservasi.kelola` di outlet perangkat. */
    private function CariPelaku(Perangkat $perangkat, string $uuidPengguna, AnggotaOutlet $anggota, string $pesanTanpaIzin): DataAnggotaOutlet
    {
        $pelaku = $anggota->Cari($perangkat->IdTenant, strtoupper($uuidPengguna), $perangkat->IdOutlet);

        if ($pelaku === null || (! $pelaku->CekIzin(IzinTenant::PenjualanBuat->value) && ! $pelaku->CekIzin(IzinTenant::ReservasiKelola->value))) {
            throw new PelanggaranAturanBisnis('TanpaIzin', $pesanTanpaIzin, 'UuidPengguna', 403);
        }

        return $pelaku;
    }
}
