<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Dukungan\Enum\JenisPengirimPesan;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Layanan\PenulisPesanTiket;
use App\Domain\Dukungan\Layanan\PenyimpanLampiran;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Dukungan\Peristiwa\TiketDukunganDibalasPelapor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengguna tenant membalas tiket (P-09). Balasan saat `MenungguPelanggan` mengembalikan tiket ke `Ditangani`.
 * Tiket `Selesai` dibuka lagi bila masih dalam 7 hari; tiket `Ditutup` atau lewat masa itu ditolak.
 */
final class BalasTiketDukungan
{
    public function __construct(
        private readonly PenulisPesanTiket $penulisPesan,
        private readonly PenyimpanLampiran $penyimpanLampiran,
    ) {}

    /**
     * @param  list<UploadedFile>  $berkas
     */
    public function Jalankan(int $idPengguna, string $namaPengguna, TiketDukungan $tiket, string $isi, array $berkas = []): TiketDukungan
    {
        $this->PastikanBisaDibalas($tiket);
        $lampiran = $this->penyimpanLampiran->Simpan($tiket->IdTenant, $tiket->Uuid, $berkas);

        try {
            return DB::transaction(function () use ($idPengguna, $namaPengguna, $tiket, $isi, $lampiran): TiketDukungan {
                $tiket = TiketDukungan::query()->lockForUpdate()->findOrFail($tiket->Id);
                $this->PastikanBisaDibalas($tiket);
                $dibukaLagi = $tiket->Status === StatusTiketDukungan::Selesai;

                if ($dibukaLagi || $tiket->Status === StatusTiketDukungan::MenungguPelanggan) {
                    $tiket->Status = StatusTiketDukungan::Ditangani;
                    $tiket->DiselesaikanPada = null;
                    $tiket->save();
                }

                if ($dibukaLagi) {
                    $this->penulisPesan->TulisSistem($tiket, 'Tiket dibuka lagi oleh pelapor.');
                }

                $this->penulisPesan->Tulis($tiket, JenisPengirimPesan::Pengguna, $isi, $namaPengguna, idPengguna: $idPengguna, lampiran: $lampiran);

                TiketDukunganDibalasPelapor::dispatch(
                    $tiket->IdTenant,
                    $tiket->Id,
                    $tiket->Uuid,
                    $tiket->Nomor,
                    $tiket->Judul,
                    $tiket->IdPenanggungJawab,
                    $dibukaLagi,
                );

                return $tiket;
            });
        } catch (Throwable $galat) {
            $this->penyimpanLampiran->Hapus($lampiran);

            throw $galat;
        }
    }

    private function PastikanBisaDibalas(TiketDukungan $tiket): void
    {
        if ($tiket->Status === StatusTiketDukungan::Ditutup) {
            throw new PelanggaranAturanBisnis('P-09', 'Tiket ini sudah ditutup. Buat tiket baru bila masalahnya muncul lagi.', 'Isi');
        }

        if ($tiket->Status === StatusTiketDukungan::Selesai && ! $tiket->CekBisaDibukaLagi()) {
            $hari = (int) config('dukungan.HariBukaUlang');

            throw new PelanggaranAturanBisnis('P-09', "Tiket selesai lebih dari {$hari} hari lalu. Buat tiket baru dan sebutkan nomor tiket ini.", 'Isi');
        }
    }
}
