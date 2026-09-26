<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Kasir\Data\DataBukaLaci;
use App\Domain\Kasir\Model\BukaLaci;
use App\Domain\Kasir\Model\Shift;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Cetak struk bagian 4 (POS-17, §19.2): buka laci kas manual tanpa transaksi di aplikasi kasir **selalu dicatat**.
 *
 * - Idempoten per `Uuid` (`Duplikat`); Uuid dipakai data lain = `UuidSudahDipakai`.
 * - Shift wajib milik perangkat pengirim. Pembuka wajib anggota outlet shift dengan izin `penjualan.buat`.
 * - Laci sudah terbuka di perangkat, jadi log tetap diterima walau ada yang janggal, dengan `PerluTinjauan`: shift
 *   tidak aktif lagi (`ShiftTidakAktif`), PIN wajib (`BukaLaciPerluPin`) tetapi tanpa penyetuju
 *   (`PenyetujuTidakAda`), atau penyetuju tanpa izin `kas.keluar.setujui` di outlet itu (`PenyetujuTidakBerwenang`).
 * - Tidak ada jurnal: membuka laci tidak memindahkan uang. Audit `laci.buka`.
 */
final class CatatBukaLaci
{
    private const TOLERANSI_JAM_DETIK = 600;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AnggotaOutlet $anggota,
        private readonly PengaturanKasirTenant $pengaturan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataBukaLaci $data): StatusItemSinkron
    {
        if (mb_strlen(trim($data->alasan)) < 3) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Isi alasan membuka laci minimal 3 karakter.', 'Alasan');
        }

        if ($data->dibukaPada->greaterThan(CarbonImmutable::now()->addSeconds(self::TOLERANSI_JAM_DETIK))) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Waktu buka laci ada di masa depan. Periksa jam perangkat.', 'DibukaPada');
        }

        try {
            return DB::transaction(fn (): StatusItemSinkron => $this->Proses($data));
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) === 1062) {
                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik buka laci ini sudah dipakai. Catat ulang di aplikasi.', 'Uuid');
            }

            throw $galat;
        }
    }

    private function Proses(DataBukaLaci $data): StatusItemSinkron
    {
        $idTenant = $this->konteks->Wajib();
        $shift = Shift::query()->where('Uuid', $data->uuidShift)->where('IdPerangkat', $data->idPerangkat)->first();

        if ($shift === null) {
            throw new PelanggaranAturanBisnis('ShiftTidakDikenal', 'Shift untuk buka laci ini tidak ditemukan di perangkat ini. Kirim data shift lebih dulu.', 'UuidShift');
        }

        $lama = BukaLaci::query()->where('Uuid', $data->uuid)->first();

        if ($lama !== null) {
            if ($lama->IdShift !== $shift->Id) {
                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik buka laci ini sudah dipakai data lain. Catat ulang di aplikasi.', 'Uuid');
            }

            return StatusItemSinkron::Duplikat;
        }

        $pembuka = $this->anggota->Cari($idTenant, $data->uuidPembuka, $shift->IdOutlet);

        if ($pembuka === null) {
            throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Pengguna ini tidak terdaftar di outlet shift ini.', 'UuidPembuka');
        }

        if (! $pembuka->CekIzin(IzinTenant::PenjualanBuat->value)) {
            throw new PelanggaranAturanBisnis('TanpaIzin', "{$pembuka->nama} tidak punya izin memakai laci kas di POS.", 'UuidPembuka', 403);
        }

        $tinjauan = [];

        if (! $shift->Status->CekAktif()) {
            $tinjauan[] = "ShiftTidakAktif: laci dibuka saat shift {$shift->Status->AmbilLabel()}";
        }

        $penyetuju = $data->uuidPenyetuju === null ? null : $this->anggota->Cari($idTenant, $data->uuidPenyetuju, $shift->IdOutlet);

        if ($data->uuidPenyetuju === null && $this->pengaturan->Ambil()->bukaLaciPerluPin) {
            $tinjauan[] = 'PenyetujuTidakAda: buka laci wajib PIN penyetuju';
        }

        if ($data->uuidPenyetuju !== null && ($penyetuju === null || ! $penyetuju->CekIzin(IzinTenant::KasKeluarSetujui->value))) {
            $tinjauan[] = 'PenyetujuTidakBerwenang: penyetuju tidak punya izin kas.keluar.setujui di outlet ini';
            $penyetuju = null;
        }

        $log = BukaLaci::query()->create([
            'Uuid' => $data->uuid,
            'IdShift' => $shift->Id,
            'IdPerangkat' => $data->idPerangkat,
            'Alasan' => trim($data->alasan),
            'DibukaOleh' => $pembuka->id,
            'DisetujuiOleh' => $penyetuju?->id,
            'DibukaPada' => $data->dibukaPada,
            'DiterimaPada' => CarbonImmutable::now(),
            'PerluTinjauan' => $tinjauan !== [],
            'AlasanTinjauan' => $tinjauan === [] ? null : mb_substr(implode('; ', $tinjauan), 0, 255),
        ]);

        $this->audit->Catat('laci.buka', $log, nilaiBaru: [
            'Alasan' => $log->Alasan,
            'Shift' => $shift->Uuid,
            'DisetujuiOleh' => $penyetuju?->nama,
            'PerluTinjauan' => $log->PerluTinjauan,
        ], idPengguna: $pembuka->id);

        return StatusItemSinkron::Diterima;
    }
}
