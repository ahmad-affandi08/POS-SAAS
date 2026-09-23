<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Layanan;

use App\Domain\Dukungan\Enum\JenisPengirimPesan;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Dukungan\Model\TiketDukunganPesan;

/**
 * Satu-satunya jalan menambah pesan tiket (P-09), dipakai sisi tenant dan tim internal. `IdTenant` pesan selalu
 * mengikuti tiket, dan waktu pesan terakhir hanya bergerak untuk pesan yang terlihat oleh pelapor.
 */
final class PenulisPesanTiket
{
    /**
     * @param  list<array{Uuid: string, NamaAsli: string, Mime: string, UkuranByte: int, Path: string}>  $lampiran
     */
    public function Tulis(
        TiketDukungan $tiket,
        JenisPengirimPesan $jenis,
        string $isi,
        ?string $namaPengirim = null,
        ?int $idPengguna = null,
        ?int $idPenggunaPengelola = null,
        bool $catatanInternal = false,
        array $lampiran = [],
    ): TiketDukunganPesan {
        $pesan = TiketDukunganPesan::query()->create([
            'IdTenant' => $tiket->IdTenant,
            'IdTiketDukungan' => $tiket->Id,
            'JenisPengirim' => $jenis,
            'IdPengguna' => $idPengguna,
            'IdPenggunaPengelola' => $idPenggunaPengelola,
            'NamaPengirim' => $namaPengirim,
            'CatatanInternal' => $catatanInternal,
            'Isi' => $isi,
            'Lampiran' => $lampiran === [] ? null : $lampiran,
        ]);

        if (! $catatanInternal) {
            $tiket->PesanTerakhirPada = $pesan->DibuatPada;
            $tiket->save();
        }

        return $pesan;
    }

    public function TulisSistem(TiketDukungan $tiket, string $isi, bool $catatanInternal = false): TiketDukunganPesan
    {
        return $this->Tulis($tiket, JenisPengirimPesan::Sistem, $isi, catatanInternal: $catatanInternal);
    }
}
