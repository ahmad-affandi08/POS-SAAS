<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pembelian\Data\DataPemasok;
use App\Domain\Pembelian\Model\Pemasok;
use Illuminate\Support\Facades\DB;

/**
 * Menambah atau mengubah pemasok (F-04 fase 1, izin `pembelian.kelola`). Kode unik per tenant (juga terhadap pemasok
 * yang sudah dihapus), disimpan huruf besar. Perubahan termin/PKP hanya berlaku untuk dokumen baru (dokumen lama
 * menyimpan snapshot). Audit `pemasok.tambah`/`pemasok.ubah`.
 */
final class SimpanPemasok
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @throws PelanggaranAturanBisnis KodeSudahDipakai
     */
    public function Jalankan(DataPemasok $data, ?Pemasok $pemasok = null): Pemasok
    {
        return DB::transaction(function () use ($data, $pemasok): Pemasok {
            $kode = mb_strtoupper(trim($data->kode));
            $dipakai = Pemasok::query()->withTrashed()
                ->where('Kode', $kode)
                ->when($pemasok !== null, fn ($kueri) => $kueri->whereKeyNot($pemasok?->Id))
                ->lockForUpdate()
                ->exists();

            if ($dipakai) {
                throw new PelanggaranAturanBisnis('KodeSudahDipakai', "Kode pemasok {$kode} sudah dipakai. Pakai kode lain.", 'Kode');
            }

            $isian = [
                'Kode' => $kode,
                'Nama' => trim($data->nama),
                'NamaKontak' => self::Bersihkan($data->namaKontak),
                'NoHp' => self::Bersihkan($data->noHp),
                'Email' => self::Bersihkan($data->email),
                'Alamat' => self::Bersihkan($data->alamat),
                'Npwp' => self::Bersihkan($data->npwp),
                'Pkp' => $data->pkp,
                'TerminHari' => $data->terminHari,
                'NamaBank' => self::Bersihkan($data->namaBank),
                'NomorRekening' => self::Bersihkan($data->nomorRekening),
                'AtasNamaRekening' => self::Bersihkan($data->atasNamaRekening),
                'Catatan' => self::Bersihkan($data->catatan),
            ];

            if ($pemasok === null) {
                $baru = Pemasok::query()->create([...$isian, 'DibuatOleh' => $data->idPengguna]);
                $this->audit->Catat('pemasok.tambah', $baru, nilaiBaru: $isian, idPengguna: $data->idPengguna);

                return $baru;
            }

            $terkunci = Pemasok::query()->whereKey($pemasok->Id)->lockForUpdate()->firstOrFail();
            $lama = array_intersect_key($terkunci->only(array_keys($isian)), $isian);
            $terkunci->fill($isian);
            $berubah = array_keys($terkunci->getDirty());
            $terkunci->save();

            if ($berubah !== []) {
                $this->audit->Catat('pemasok.ubah', $terkunci, array_intersect_key($lama, array_flip($berubah)), array_intersect_key($isian, array_flip($berubah)), idPengguna: $data->idPengguna);
            }

            return $terkunci;
        }, 3);
    }

    private static function Bersihkan(?string $teks): ?string
    {
        $teks = $teks === null ? null : trim($teks);

        return $teks === '' ? null : $teks;
    }
}
