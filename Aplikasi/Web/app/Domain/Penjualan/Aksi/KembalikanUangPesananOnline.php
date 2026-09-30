<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\PesananOnline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-17 toko online bagian 2, **J-17.2**: uang muka pesanan online dikembalikan ke pelanggan — Dr `Uang Muka
 * Pelanggan`, Cr akun kas/bank yang dipilih operator. Dipakai saat pesanan yang sudah dibayar ternyata **ditolak,
 * dibatalkan, atau hangus**, dan saat pesanan selesai menyisakan uang muka yang tidak terpakai.
 *
 * **Pengembaliannya dicatat, bukan dijalankan.** Toko mentransfer sendiri (atau menyerahkan tunai) lalu mencatatnya
 * di sini: refund otomatis ke gerbang membutuhkan API refund per penyedia yang belum ada di `Paket` integrasi, dan
 * mengaku-ngaku sudah mengembalikan uang yang belum berpindah rekening lebih berbahaya daripada satu langkah manual.
 *
 * Jurnalnya terpisah dari J-17.1 pada sumber yang sama (`kunciSumber` `Refund`), jadi keduanya bisa ditelusuri
 * berdampingan dan satu pesanan hanya bisa dikembalikan sekali. Periode harus **terbuka**: berbeda dengan uang masuk
 * yang tidak boleh ditolak, pengembalian adalah tindakan yang dipilih operator dan tanggalnya bisa ditunggu.
 *
 * Galat: 422 `AlasanWajib`, 422 `AkunKasBankWajib`, 409 `PesananBelumFinal`, 409 `TidakAdaSisaUangMuka`,
 * 409 `SudahDikembalikan`.
 */
final class KembalikanUangPesananOnline
{
    /** Jurnal pengembalian terpisah dari jurnal uang muka (J-17.1) pada sumber yang sama. */
    public const KUNCI_JURNAL = 'Refund';

    public function __construct(
        private readonly DaftarAkunPilihan $akun,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PesananOnline $pesanan, string $uuidAkun, string $alasan, int $idPengguna): PesananOnline
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan 5–255 karakter.', 'Alasan');
        }

        $idAkun = ($this->akun->CariKasBankDariUuid(strtoupper($uuidAkun))['Id'] ?? null)
            ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas/bank aktif untuk mengembalikan uang pelanggan.', 'UuidAkun');

        return DB::transaction(function () use ($pesanan, $idAkun, $alasan, $idPengguna): PesananOnline {
            $pesanan = PesananOnline::query()->whereKey($pesanan->Id)->lockForUpdate()->firstOrFail();

            if ($pesanan->DikembalikanPada !== null) {
                throw new PelanggaranAturanBisnis('SudahDikembalikan', "Uang muka pesanan {$pesanan->Nomor} sudah pernah dikembalikan.", 'Umum', 409);
            }

            if (! in_array($pesanan->Status, self::STATUS_BOLEH, true)) {
                throw new PelanggaranAturanBisnis(
                    'PesananBelumFinal',
                    "Pesanan {$pesanan->Nomor} masih {$pesanan->Status->AmbilLabel()}. Tolak, batalkan, atau selesaikan dulu sebelum mengembalikan uangnya.",
                    'Status',
                    409,
                );
            }

            $sisa = $pesanan->AmbilSisaUangMuka();

            if ($sisa->Bandingkan(Uang::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('TidakAdaSisaUangMuka', "Pesanan {$pesanan->Nomor} tidak punya uang muka yang tersisa.", 'Umum', 409);
            }

            $tanggal = CarbonImmutable::parse($this->tanggalBisnis->Hitung($pesanan->IdOutlet)->toDateString());
            $this->penjagaPeriode->PastikanTerbuka($tanggal);

            $pesanan->IdJurnalRefund = $this->postingJurnal->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::PesananOnline,
                idSumber: $pesanan->Id,
                uuidSumber: $pesanan->Uuid,
                nomorSumber: $pesanan->Nomor,
                tanggal: $tanggal,
                keterangan: mb_substr("Pengembalian uang muka pesanan online {$pesanan->Nomor}: {$alasan}", 0, 255),
                baris: [
                    DataBarisJurnal::Debit(PeranAkun::UangMukaPelanggan, $sisa, $pesanan->IdOutlet, $pesanan->Nomor),
                    new DataBarisJurnal(null, $idAkun, $pesanan->IdOutlet, Uang::Nol(), $sisa, "Pengembalian {$pesanan->Nomor}"),
                ],
                idPengguna: $idPengguna,
                kunciSumber: self::KUNCI_JURNAL,
            ))->idJurnal;
            $pesanan->DikembalikanPada = now();
            $pesanan->DiubahOleh = $idPengguna;
            $pesanan->save();

            $this->audit->Catat('pesanan-online.kembalikan-uang', $pesanan, nilaiBaru: [
                'Nomor' => $pesanan->Nomor,
                'Jumlah' => $sisa->KeString(),
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $pesanan;
        });
    }

    /** Pesanan yang tidak akan ditagihkan lagi, jadi uang mukanya memang harus keluar. */
    private const STATUS_BOLEH = [
        StatusPesananOnline::Ditolak,
        StatusPesananOnline::Dibatalkan,
        StatusPesananOnline::Kedaluwarsa,
        StatusPesananOnline::Selesai,
    ];
}
