<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\LayananReservasi;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Data\DataReservasi;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Enum\SumberReservasi;
use App\Domain\Pemenuhan\Kueri\PengaturanReservasiTenant;
use App\Domain\Pemenuhan\Kueri\SlotReservasi;
use App\Domain\Pemenuhan\Model\PengaturanReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F-07 mode service: buat reservasi. Layanan harus jasa berdurasi (online: juga `TampilOnline`); slot dihitung ulang
 * di server (`SlotReservasi`) sehingga jam di luar jadwal staf atau yang bentrok ditolak. Staf kosong = staf pertama
 * yang kosong pada jam itu. Pembuatan dikunci per staf (kunci cache atomik) agar dua pemesan tidak mendapat slot yang
 * sama. Reservasi online berstatus `Menunggu` bila toko tidak mengaktifkan konfirmasi otomatis, selain itu
 * `Dikonfirmasi`. Pelanggan ditautkan bila nomor HP sudah terdaftar. Nomor `RS/YYYY/MM/NNNN`.
 */
final class BuatReservasi
{
    public const MAKS_AKTIF_PER_HP = 3;

    public function __construct(
        private readonly LayananReservasi $layanan,
        private readonly JadwalStafReservasi $staf,
        private readonly SlotReservasi $slot,
        private readonly PengaturanReservasiTenant $pengaturan,
        private readonly ZonaWaktuOutlet $zona,
        private readonly IdentitasPelanggan $pelanggan,
        private readonly PenomorDokumen $penomor,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idTenant, DataReservasi $data): Reservasi
    {
        $online = $data->sumber === SumberReservasi::Online;
        $atur = $this->pengaturan->Ambil();
        $layanan = $this->layanan->Cari($data->uuidLayanan, $online)
            ?? throw new PelanggaranAturanBisnis('LayananTidakDitemukan', 'Layanan tidak ditemukan atau tidak bisa dipesan.', 'UuidLayanan');
        $noHp = NomorHp::Normalisasi($data->noHp)
            ?? throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Nomor HP tidak valid. Contoh: 0812-3456-7890.', 'NoHp');
        $nama = trim($data->namaPelanggan);

        if ($nama === '') {
            throw new PelanggaranAturanBisnis('NamaWajib', 'Nama pelanggan wajib diisi.', 'NamaPelanggan');
        }

        $idStaf = null;

        if ($data->uuidStaf !== null && $data->uuidStaf !== '') {
            $idStaf = ($this->staf->CariStaf($data->uuidStaf) ?? throw new PelanggaranAturanBisnis('StafTidakDitemukan', 'Staf tidak ditemukan.', 'UuidStaf'))['Id'];
        }

        $zona = $this->zona->Ambil($data->idOutlet);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data->tanggal) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data->jam) !== 1) {
            throw new PelanggaranAturanBisnis('WaktuTidakValid', 'Tanggal atau jam tidak valid.', 'Jam');
        }

        $mulai = CarbonImmutable::parse("{$data->tanggal} {$data->jam}", $zona);
        $sekarang = CarbonImmutable::now();
        $palingCepat = $online ? $sekarang->addMinutes($atur->MinimalMenitSebelum) : $sekarang->subMinutes(5);

        if ($online && $mulai->gt($sekarang->setTimezone($zona)->startOfDay()->addDays($atur->BatasHariKeDepan + 1))) {
            throw new PelanggaranAturanBisnis('TerlaluJauh', "Reservasi paling jauh {$atur->BatasHariKeDepan} hari ke depan.", 'Tanggal');
        }

        // Anti-spam reservasi online: paling banyak MAKS_AKTIF_PER_HP reservasi mendatang per nomor HP.
        if ($online && Reservasi::query()->where('NoHp', $noHp)->whereIn('Status', StatusReservasi::AmbilNilaiMemakaiSlot())->where('MulaiPada', '>', $sekarang)->count() >= self::MAKS_AKTIF_PER_HP) {
            throw new PelanggaranAturanBisnis('TerlaluBanyak', 'Nomor ini sudah punya '.self::MAKS_AKTIF_PER_HP.' reservasi aktif. Hubungi toko untuk menambah.', 'NoHp', 429);
        }

        $kandidat = $this->CariStafKosong($data, $layanan['DurasiMenit'], $idStaf, $atur, $palingCepat);
        $idPelanggan = $this->pelanggan->CariIdDariNoHp($noHp);

        foreach ($kandidat as $idKaryawan) {
            try {
                $reservasi = Cache::lock("reservasi-staf:{$idTenant}:{$idKaryawan}", 10)->block(5, function () use ($data, $layanan, $idKaryawan, $atur, $palingCepat, $mulai, $noHp, $nama, $idPelanggan, $online): ?Reservasi {
                    // Dihitung ulang di dalam kunci: slot bisa baru saja diambil pemesan lain.
                    if (! in_array($idKaryawan, $this->CariStafKosong($data, $layanan['DurasiMenit'], $idKaryawan, $atur, $palingCepat), true)) {
                        return null;
                    }

                    return DB::transaction(function () use ($data, $layanan, $idKaryawan, $atur, $mulai, $noHp, $nama, $idPelanggan, $online): Reservasi {
                        $status = $online && ! $atur->KonfirmasiOtomatis ? StatusReservasi::Menunggu : StatusReservasi::Dikonfirmasi;
                        $reservasi = Reservasi::query()->create([
                            'IdOutlet' => $data->idOutlet,
                            'Nomor' => $this->penomor->AmbilNomorBerikutnya(JenisDokumenBernomor::Reservasi, $mulai->format('Y-m')),
                            'IdPelanggan' => $idPelanggan,
                            'NamaPelanggan' => mb_substr($nama, 0, 100),
                            'NoHp' => $noHp,
                            'IdProduk' => $layanan['Id'],
                            'IdKaryawan' => $idKaryawan,
                            'MulaiPada' => $mulai->utc(),
                            'SelesaiPada' => $mulai->addMinutes($layanan['DurasiMenit'])->utc(),
                            'Status' => $status,
                            'Sumber' => $data->sumber,
                            'Catatan' => $data->catatan === null || trim($data->catatan) === '' ? null : mb_substr(trim($data->catatan), 0, 255),
                            'KodeAkses' => Str::upper(Str::random(12)),
                            'DibuatOleh' => $data->idPengguna,
                        ]);
                        $this->riwayat->Catat(Reservasi::JENIS_DOKUMEN, $reservasi->Id, null, $status->value, $data->idPengguna);

                        if ($data->idPengguna !== null) {
                            $this->audit->Catat('reservasi.buat', $reservasi, null, [
                                'Nomor' => $reservasi->Nomor,
                                'Layanan' => $layanan['Nama'],
                                'MulaiPada' => $mulai->toIso8601String(),
                            ], idPengguna: $data->idPengguna);
                        }

                        return $reservasi;
                    });
                });
            } catch (LockTimeoutException) {
                $reservasi = null;
            }

            if ($reservasi !== null) {
                return $reservasi;
            }
        }

        throw new PelanggaranAturanBisnis('SlotTidakTersedia', 'Jam ini sudah tidak tersedia. Pilih jam lain.', 'Jam', 409);
    }

    /**
     * Id staf yang kosong pada jam [data->jam] (urut nama), dibatasi [idStaf] bila dipilih.
     *
     * @return list<int>
     */
    private function CariStafKosong(DataReservasi $data, int $durasi, ?int $idStaf, PengaturanReservasi $atur, CarbonImmutable $palingCepat): array
    {
        foreach ($this->slot->Hitung($data->idOutlet, $data->tanggal, $durasi, $idStaf, $atur, $palingCepat) as $s) {
            if ($s['Jam'] === $data->jam) {
                return array_column($s['Staf'], 'Id');
            }
        }

        return [];
    }
}
