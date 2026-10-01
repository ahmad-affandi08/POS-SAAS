<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\StatusMutasiBank;
use App\Domain\Akuntansi\Layanan\PencariKandidatMutasi;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\MutasiBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan atas satu mutasi rekening koran (FIN-09): **cocokkan** ke baris jurnal akun yang sama di jurnal `uuidJurnal`
 * (harus termasuk kandidat: nominal & sisi sama, ±5 hari, belum dipakai), **abaikan** dengan alasan (biaya admin yang
 * sudah dicatat gabungan, transfer antar rekening sendiri yang dicatat di tempat lain, dll.), atau **batalkan
 * keputusan** (kembali Belum cocok). Tidak menulis jurnal. Audit `mutasi-bank.cocok|abaikan|batal`.
 */
final class PutuskanMutasiBank
{
    public function __construct(
        private readonly PencariKandidatMutasi $kandidat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(MutasiBank $mutasi, string $keputusan, ?string $uuidJurnal, ?string $alasan, int $idPengguna): MutasiBank
    {
        return DB::transaction(function () use ($mutasi, $keputusan, $uuidJurnal, $alasan, $idPengguna): MutasiBank {
            $mutasi = MutasiBank::query()->whereKey($mutasi->Id)->lockForUpdate()->firstOrFail();

            if ($keputusan === 'Batal') {
                if ($mutasi->Status === StatusMutasiBank::BelumCocok) {
                    throw new PelanggaranAturanBisnis('BelumDiputuskan', 'Mutasi ini belum dicocokkan atau diabaikan.');
                }

                $mutasi->fill(['Status' => StatusMutasiBank::BelumCocok, 'IdJurnalDetail' => null, 'AlasanAbaikan' => null, 'DiputuskanOleh' => $idPengguna, 'DiputuskanPada' => now()])->save();
                $this->audit->Catat('mutasi-bank.batal', $mutasi, idPengguna: $idPengguna);

                return $mutasi;
            }

            if ($mutasi->Status !== StatusMutasiBank::BelumCocok) {
                throw new PelanggaranAturanBisnis('SudahDiputuskan', 'Mutasi ini sudah dicocokkan atau diabaikan. Batalkan dulu keputusannya.');
            }

            if ($keputusan === 'Abaikan') {
                $alasan = trim((string) $alasan);

                if (mb_strlen($alasan) < 5) {
                    throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan mengabaikan mutasi ini, minimal 5 karakter.', 'Alasan');
                }

                $mutasi->fill(['Status' => StatusMutasiBank::Diabaikan, 'AlasanAbaikan' => mb_substr($alasan, 0, 255), 'DiputuskanOleh' => $idPengguna, 'DiputuskanPada' => now()])->save();
                $this->audit->Catat('mutasi-bank.abaikan', $mutasi, nilaiBaru: ['Alasan' => $alasan], idPengguna: $idPengguna);

                return $mutasi;
            }

            $jurnal = $uuidJurnal === null ? null : Jurnal::query()->where('Uuid', $uuidJurnal)->first();
            $baris = $jurnal === null ? null : $this->kandidat->Cari($mutasi, 50)->first(fn ($d): bool => $d->IdJurnal === $jurnal->Id);

            if ($baris === null) {
                throw new PelanggaranAturanBisnis('KandidatTidakValid', 'Jurnal itu tidak cocok dengan mutasi ini (akun, nominal, atau tanggal berbeda, atau sudah dicocokkan).', 'Jurnal');
            }

            $mutasi->fill(['Status' => StatusMutasiBank::Cocok, 'IdJurnalDetail' => $baris->Id, 'DiputuskanOleh' => $idPengguna, 'DiputuskanPada' => now()])->save();
            $this->audit->Catat('mutasi-bank.cocok', $mutasi, nilaiBaru: ['Jurnal' => $jurnal?->Nomor], idPengguna: $idPengguna);

            return $mutasi;
        });
    }
}
