<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Data\HasilPelunasanLangganan;
use App\Domain\Tenant\Enum\JenisTagihanLangganan;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Carbon\CarbonImmutable;

/**
 * Inti pelunasan tagihan langganan (BR-P08.9): pembayaran Diterima → tagihan Lunas → langganan Aktif dengan paket,
 * siklus, dan periode baru.
 *
 * Dipakai **kedua** jalur pembayaran P-08 langkah 3 supaya hasilnya tidak bisa berbeda:
 * - transfer manual, diverifikasi Keuangan/Super Admin (`TerimaPembayaranLangganan`);
 * - gerbang pembayaran, dari notifikasi webhook (`TerimaNotifikasiBillingLangganan`).
 *
 * Layanan ini **tidak** memuat, mengunci, atau mengambil data apa pun: ketiga model harus sudah dikunci pemanggil
 * dengan urutan Langganan → Tagihan → Pembayaran. Alasannya, kedua jalur memuatnya dari kueri berbeda — pengelola
 * lintas tenant, atau tenant lewat scope `MilikTenant` — sedangkan keputusan dan tulisannya harus sama.
 * Audit juga milik pemanggil, karena pelakunya berbeda.
 */
final class PelunasTagihanLangganan
{
    public function __construct(private readonly PenghitungPeriodeLangganan $periode) {}

    /**
     * @param  CarbonImmutable|null  $mulaiPaketSebelumnya  Jangkar grandfathering (BR-P04.1) dari tagihan lunas
     *                                                      terakhir untuk paket yang sama; hanya dipakai bila
     *                                                      tagihan ini memang menyambung periode berjalan.
     *
     * @throws PelanggaranAturanBisnis
     */
    public function Lunasi(
        Langganan $langganan,
        TagihanLangganan $tagihan,
        PembayaranLangganan $pembayaran,
        Uang $diterima,
        CarbonImmutable $sekarang,
        ?int $idVerifikatorPengelola = null,
        ?CarbonImmutable $mulaiPaketSebelumnya = null,
    ): HasilPelunasanLangganan {
        if ($pembayaran->Status !== StatusPembayaranLangganan::Menunggu) {
            throw new PelanggaranAturanBisnis('SudahDiverifikasi', "Pembayaran ini sudah {$pembayaran->Status->AmbilLabel()}.");
        }

        if (! $tagihan->Status->CekTerbuka()) {
            throw new PelanggaranAturanBisnis('TagihanTidakTerbuka', "Tagihan {$tagihan->Nomor} sudah {$tagihan->Status->AmbilLabel()}.");
        }

        if (! $diterima->SamaDengan($tagihan->AmbilTotal()) || ! $diterima->SamaDengan($pembayaran->AmbilJumlah())) {
            throw new PelanggaranAturanBisnis(
                'JumlahTidakCocok',
                'Jumlah diterima harus sama dengan total tagihan '.$tagihan->AmbilTotal()->FormatRupiah().'. Bila berbeda, tolak dengan alasan.',
                'JumlahDiterima',
            );
        }

        if ($langganan->CekDitangguhkanManual()) {
            throw new PelanggaranAturanBisnis(
                'BR-P07.4',
                'Tenant ini ditangguhkan manual oleh Super Admin. Penangguhan harus dicabut lewat "Aktifkan kembali" di halaman tenant sebelum pembayaran bisa diterima.',
            );
        }

        if ($langganan->Status !== StatusLangganan::Aktif && ! $langganan->Status->BisaBerubahKe(StatusLangganan::Aktif)) {
            throw new PelanggaranAturanBisnis('LanggananTidakBisaAktif', "Langganan berstatus {$langganan->Status->AmbilLabel()} tidak bisa diaktifkan dari tagihan.");
        }

        // Menyambung periode berjalan hanya bila tagihan perpanjangan untuk paket yang sama persis; selain itu
        // periode dimulai saat pembayaran diterima, sehingga masa tenggang tidak menjadi hari gratis.
        $lanjutan = $tagihan->Jenis === JenisTagihanLangganan::Perpanjangan
            && in_array($langganan->Status, [StatusLangganan::Aktif, StatusLangganan::Tertunggak], true)
            && $langganan->IdPaket === $tagihan->IdPaket;
        $periode = $this->periode->Hitung(
            $lanjutan ? JenisTagihanLangganan::Perpanjangan : JenisTagihanLangganan::Aktivasi,
            $tagihan->Siklus,
            $sekarang,
            $langganan->PeriodeSelesai,
        );
        $mulaiPaket = $lanjutan ? $mulaiPaketSebelumnya : null;

        $statusTagihanLama = $tagihan->Status->value;
        $langgananLama = [
            'Status' => $langganan->Status->value,
            'IdPaket' => $langganan->IdPaket,
            'SiklusTagihan' => $langganan->SiklusTagihan->value,
            'PeriodeMulai' => $langganan->PeriodeMulai?->toIso8601ZuluString(),
            'PeriodeSelesai' => $langganan->PeriodeSelesai?->toIso8601ZuluString(),
        ];

        $pembayaran->update([
            'Status' => StatusPembayaranLangganan::Diterima,
            'IdPenggunaPengelolaVerifikator' => $idVerifikatorPengelola,
            'DiverifikasiPada' => $sekarang,
            'JumlahDiterima' => $diterima->KeString(),
        ]);

        $tagihan->update([
            'Status' => StatusTagihanLangganan::Lunas,
            'DibayarPada' => $sekarang,
            'PeriodeMulai' => $periode['Mulai'],
            'PeriodeSelesai' => $periode['Selesai'],
            'MulaiLanggananPaket' => ($mulaiPaket ?? $periode['Mulai'])->toDateString(),
        ]);

        $langganan->update([
            'Status' => StatusLangganan::Aktif,
            'IdPaket' => $tagihan->IdPaket,
            'SiklusTagihan' => $tagihan->Siklus,
            'PeriodeMulai' => $periode['Mulai'],
            'PeriodeSelesai' => $periode['Selesai'],
        ]);

        return new HasilPelunasanLangganan($periode, $langgananLama, $statusTagihanLama, $lanjutan);
    }
}
