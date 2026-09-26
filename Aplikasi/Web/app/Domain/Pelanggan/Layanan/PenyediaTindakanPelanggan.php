<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Bersama\Tindakan\Layanan\PembuatButirTinjauan;
use App\Domain\Pelanggan\Enum\StatusPemakaianSesi;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Model\PemakaianSesi;
use App\Domain\Pelanggan\Model\Piutang;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kotak Tindakan domain Pelanggan (D-23 C, izin lihat `pelanggan.lihat`, dibatasi outlet akses):
 * - pemakaian paket sesi yang diterima dengan `PerluTinjauan` dan belum ditandai dicek;
 * - piutang pelanggan yang lewat jatuh tempo (selesai sendiri saat dilunasi);
 * - paket sesi aktif yang pelanggannya belum dikenal (penjualan offline dengan pelanggan baru yang belum tersinkron).
 */
final class PenyediaTindakanPelanggan implements PenyediaTindakan
{
    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('pelanggan.lihat')) {
            return [];
        }

        $idOutlet = $konteks->idOutletBoleh;
        $hari = $konteks->hariIni->toDateString();
        $piutang = Piutang::query()
            ->whereIn('Status', [StatusPiutang::BelumLunas->value, StatusPiutang::DibayarSebagian->value])
            ->where('JatuhTempo', '<', $hari)
            ->when($idOutlet !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutlet));
        $jumlahPiutang = (clone $piutang)->count();
        $totalPiutang = Uang::Dari((string) ((clone $piutang)->sum(DB::raw('`Jumlah` - `JumlahDibayar` - `JumlahDikurangi`')) ?: '0'));
        $tanpaPelanggan = SaldoSesi::query()->whereNull('IdPelanggan')->where('Status', StatusSaldoSesi::Aktif->value)
            ->when($idOutlet !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutlet));
        $jumlahTanpa = (clone $tanpaPelanggan)->count();

        return [
            PembuatButirTinjauan::Buat(
                $this->KueriPemakaian($idOutlet),
                'PemakaianSesi',
                'PemakaianSesi',
                'pemakaian-sesi.tinjauan',
                'Pelanggan',
                'Pemakaian paket sesi perlu dicek',
                'Sesi dipakai di kasir dengan catatan (misal sisa kurang, layanan di luar paket).',
                '/kelola/pelanggan/saldo-sesi',
                fn (PemakaianSesi $p): DataRincianTindakan => new DataRincianTindakan($p->Uuid, ($p->NamaProduk ?? 'Pemakaian sesi').' ('.$p->Jumlah.' sesi)', $p->AlasanTinjauan, $p->TanggalBisnis->toDateString(), '/kelola/pelanggan/pemakaian-sesi/'.$p->Uuid),
                tingkat: TingkatTindakan::Perhatian,
            ),
            new DataButirTindakan(
                'piutang.lewat-jatuh-tempo',
                'Pelanggan',
                TingkatTindakan::Perhatian,
                'Piutang pelanggan lewat jatuh tempo',
                'Total sisa '.$totalPiutang->FormatRupiah().'. Tagih pelanggan atau catat pelunasannya.',
                $jumlahPiutang,
                '/kelola/piutang?saring[Umur]=Hari0Sampai30,Hari31Sampai60,Hari61Sampai90,LebihDari90',
                'Lihat piutang',
                $jumlahPiutang === 0 ? [] : array_values($piutang->orderBy('JatuhTempo')->limit(DataButirTindakan::BATAS_RINCIAN)->get()->map(
                    fn (Piutang $p): DataRincianTindakan => new DataRincianTindakan(
                        $p->Uuid,
                        $p->Nomor,
                        'Sisa '.Uang::Dari($p->Jumlah)->Kurangi(Uang::Dari($p->JumlahDibayar))->Kurangi(Uang::Dari($p->JumlahDikurangi))->FormatRupiah().', jatuh tempo '.$p->JatuhTempo->toDateString(),
                        $p->JatuhTempo->toDateString(),
                        '/kelola/piutang?cari='.rawurlencode($p->Nomor),
                    ),
                )->all()),
            ),
            new DataButirTindakan(
                'saldo-sesi.tanpa-pelanggan',
                'Pelanggan',
                TingkatTindakan::Info,
                'Paket sesi belum bertuan',
                'Terjual ke pelanggan yang belum tersinkron. Pastikan perangkat kasir sudah mengirim data pelanggannya.',
                $jumlahTanpa,
                '/kelola/pelanggan/saldo-sesi?saring[TanpaPelanggan]=Ya',
                'Lihat paket',
            ),
        ];
    }

    public function AmbilJenisDokumen(): array
    {
        return ['PemakaianSesi'];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return $jenisDokumen === 'PemakaianSesi' ? PembuatButirTinjauan::Saring($this->KueriPemakaian(null), 'PemakaianSesi', $uuid) : [];
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @return Builder<PemakaianSesi>
     */
    private function KueriPemakaian(?array $idOutlet): Builder
    {
        return PemakaianSesi::query()
            ->where('PemakaianSesi.PerluTinjauan', true)
            ->where('PemakaianSesi.Status', StatusPemakaianSesi::Diterima->value)
            ->when($idOutlet !== null, fn ($k) => $k->whereIn('PemakaianSesi.IdOutlet', $idOutlet));
    }
}
