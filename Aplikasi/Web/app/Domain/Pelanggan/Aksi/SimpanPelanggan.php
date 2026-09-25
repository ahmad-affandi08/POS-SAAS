<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Data\DataPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Penjualan\Aksi\SiapkanMetodeTempo;
use Illuminate\Support\Facades\DB;

/**
 * Menambah atau mengubah pelanggan dari back-office (F-16a, izin `pelanggan.kelola`). Nomor HP dinormalisasi dan
 * unik per tenant (`NoHpSudahTerdaftar`). Audit `pelanggan.tambah`/`pelanggan.ubah` menyimpan nomor HP tersamar (data
 * pribadi, UU PDP).
 */
final class SimpanPelanggan
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly SiapkanMetodeTempo $siapkanTempo,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis NoHpTidakValid, NoHpSudahTerdaftar
     */
    public function Jalankan(DataPelanggan $data, ?Pelanggan $pelanggan = null): Pelanggan
    {
        $noHp = NomorHp::Normalisasi($data->noHp);

        if ($noHp === null) {
            throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Nomor HP tidak valid. Contoh: 0812-3456-7890.', 'NoHp');
        }

        return DB::transaction(function () use ($data, $pelanggan, $noHp): Pelanggan {
            $pemilik = Pelanggan::query()
                ->where('NoHp', $noHp)
                ->when($pelanggan !== null, fn ($kueri) => $kueri->whereKeyNot($pelanggan?->Id))
                ->lockForUpdate()
                ->first();

            if ($pemilik !== null) {
                throw new PelanggaranAturanBisnis('NoHpSudahTerdaftar', "Nomor HP ini sudah terdaftar atas nama {$pemilik->Nama}.", 'NoHp');
            }

            $isian = [
                'Nama' => trim($data->nama),
                'NoHp' => $noHp,
                'Email' => self::Bersihkan($data->email),
                'TanggalLahir' => $data->tanggalLahir?->toDateString(),
                'Alamat' => self::Bersihkan($data->alamat),
                'Tag' => $data->tag === [] ? null : $data->tag,
                'Catatan' => self::Bersihkan($data->catatan),
                'SetujuPemasaran' => $data->setujuPemasaran,
            ];

            if ($data->aturKredit) {
                $isian['LimitKredit'] = $data->limitKredit === null || $data->limitKredit->BernilaiNol() ? null : $data->limitKredit->KeString();
                $isian['TerminHari'] = $data->terminHari;

                // F-12: limit kredit pertama di tenant = metode Tempo disiapkan agar muncul di aplikasi kasir.
                if ($isian['LimitKredit'] !== null) {
                    $this->siapkanTempo->Jalankan($data->idPengguna);
                }
            }

            if ($pelanggan === null) {
                $baru = Pelanggan::query()->create([...$isian, 'DibuatOleh' => $data->idPengguna]);
                $this->audit->Catat('pelanggan.tambah', $baru, nilaiBaru: self::UntukAudit($isian), idPengguna: $data->idPengguna);

                return $baru;
            }

            $terkunci = Pelanggan::query()->whereKey($pelanggan->Id)->lockForUpdate()->firstOrFail();
            $lama = [
                ...$terkunci->only(array_keys($isian)),
                'TanggalLahir' => $terkunci->TanggalLahir?->toDateString(),
            ];

            if ($data->aturKredit) {
                $lama['LimitKredit'] = $terkunci->LimitKredit === null ? null : Uang::Dari($terkunci->LimitKredit)->KeString();
            }

            $terkunci->fill($isian);
            $berubah = array_keys(array_filter($isian, fn (mixed $nilai, string $kolom): bool => $nilai !== ($lama[$kolom] ?? null), ARRAY_FILTER_USE_BOTH));
            $terkunci->save();

            if ($berubah !== []) {
                $kunci = array_flip($berubah);
                $this->audit->Catat(
                    'pelanggan.ubah',
                    $terkunci,
                    self::UntukAudit(array_intersect_key($lama, $kunci)),
                    self::UntukAudit(array_intersect_key($isian, $kunci)),
                    idPengguna: $data->idPengguna,
                );
            }

            return $terkunci;
        }, 3);
    }

    /**
     * Nomor HP disamarkan di log audit.
     *
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed>
     */
    private static function UntukAudit(array $nilai): array
    {
        if (isset($nilai['NoHp']) && is_string($nilai['NoHp'])) {
            $nilai['NoHp'] = NomorHp::Samarkan($nilai['NoHp']);
        }

        return $nilai;
    }

    private static function Bersihkan(?string $teks): ?string
    {
        $teks = $teks === null ? null : trim($teks);

        return $teks === '' ? null : $teks;
    }
}
