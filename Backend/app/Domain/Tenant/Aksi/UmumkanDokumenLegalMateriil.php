<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Organisasi\Kueri\PemilikTenant;
use App\Domain\Tenant\Kueri\PersetujuanLegalTertunda;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\PengumumanDokumenLegal;
use App\Domain\Tenant\Surel\PengumumanDokumenLegal as SurelPengumumanDokumenLegal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pengumuman versi materiil dokumen legal ke semua Owner (BR-P06.5), dijalankan terjadwal harian. Setiap versi yang
 * terbit dan belum berlaku dikirim sekali per pengguna (penanda `PengumumanDokumenLegal`); Owner yang bergabung di
 * tengah masa pengumuman ikut menerima pada putaran berikutnya. Email yang gagal dicoba lagi besok.
 */
final class UmumkanDokumenLegalMateriil
{
    public function __construct(
        private readonly PersetujuanLegalTertunda $persetujuan,
        private readonly PemilikTenant $pemilik,
    ) {}

    /**
     * @return int jumlah email yang terkirim
     */
    public function Jalankan(): int
    {
        $terkirim = 0;

        foreach ($this->persetujuan->AmbilPengumuman(now()) as $dokumen) {
            $sudah = array_flip(array_map('intval', PengumumanDokumenLegal::query()
                ->where('IdDokumenLegal', $dokumen->Id)
                ->pluck('IdPengguna')
                ->all()));

            foreach ($this->pemilik->AmbilSemua() as $penerima) {
                if (isset($sudah[$penerima['Id']])) {
                    continue;
                }

                try {
                    Mail::to($penerima['Email'])->send($this->BuatSurel($dokumen, $penerima['Nama']));
                } catch (Throwable $galat) {
                    Log::error('Email pengumuman dokumen legal gagal dikirim.', ['IdDokumenLegal' => $dokumen->Id, 'Pesan' => $galat->getMessage()]);

                    continue;
                }

                PengumumanDokumenLegal::query()->create([
                    'IdDokumenLegal' => $dokumen->Id,
                    'IdPengguna' => $penerima['Id'],
                    'DikirimPada' => now(),
                ]);
                $terkirim++;
            }
        }

        return $terkirim;
    }

    private function BuatSurel(DokumenLegal $dokumen, string $nama): SurelPengumumanDokumenLegal
    {
        return new SurelPengumumanDokumenLegal(
            nama: $nama,
            labelDokumen: $dokumen->Jenis->AmbilLabel(),
            versi: $dokumen->Versi,
            berlakuMulai: $dokumen->BerlakuMulai->translatedFormat('j M Y'),
            ringkasanPerubahan: $dokumen->RingkasanPerubahan,
            tautan: route('legal.tampil', ['jenis' => Str::kebab($dokumen->Jenis->value), 'versi' => $dokumen->Versi]),
        );
    }
}
