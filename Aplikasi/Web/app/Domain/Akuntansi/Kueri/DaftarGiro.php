<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\ArahGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Model\Giro;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use Illuminate\Support\Collection;

/**
 * Daftar giro/cek mundur (v3.42, `/kelola/akuntansi/giro`): saring `Status` & `Arah`, cari nomor giro, bank, pihak,
 * nomor dokumen asal, atau Uuid persis (tautan dari jurnal & Kotak Tindakan). Ringkasan: jumlah & nilai giro masuk dan
 * keluar yang masih menunggu.
 */
final class DaftarGiro
{
    public const KOLOM_URUT = ['TanggalJatuhTempo', 'TanggalTerima', 'Jumlah'];

    public const KOLOM_SARING = ['Status', 'Arah'];

    public const URUT_BAWAAN = 'TanggalJatuhTempo';

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}, Ringkasan: array<string, string|int>}
     */
    public function Ambil(DataPermintaanTabel $p): array
    {
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusGiro $s): string => $s->value, StatusGiro::cases()));
        $arah = $p->AmbilDaftar('Arah', array_map(fn (ArahGiro $a): string => $a->value, ArahGiro::cases()));
        $pola = PenerapKueriTabel::PolaCari($p->cari);
        $kueri = Giro::query()
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($arah !== [], fn ($k) => $k->whereIn('Arah', $arah))
            ->when($p->cari !== '', fn ($k) => $k->where(fn ($d) => $d->where('NomorGiro', 'like', $pola)->orWhere('NamaBank', 'like', $pola)
                ->orWhere('NamaPihak', 'like', $pola)->orWhere('NomorSumber', 'like', $pola)->orWhere('Uuid', mb_strtoupper(trim($p->cari)))));

        $hasil = PenerapKueriTabel::Terapkan($kueri, $p, array_combine(self::KOLOM_URUT, self::KOLOM_URUT), function (Collection $baris): array {
            /** @var Collection<int, Giro> $baris */
            $jurnal = Jurnal::query()->whereKey(array_values(array_filter($baris->pluck('IdJurnalCair')->all(), 'is_int')))->get(['Id', 'Uuid', 'Nomor'])->keyBy('Id');

            $tautan = function (?int $id) use ($jurnal): ?array {
                $satu = $id === null ? null : $jurnal->get($id);

                return $satu === null ? null : ['Uuid' => $satu->Uuid, 'Nomor' => $satu->Nomor];
            };

            return array_values($baris->map(fn (Giro $g): array => [
                'Uuid' => $g->Uuid,
                'Arah' => $g->Arah->value,
                'NomorGiro' => $g->NomorGiro,
                'NamaBank' => $g->NamaBank,
                'NamaPihak' => $g->NamaPihak,
                'NomorSumber' => $g->NomorSumber,
                'TanggalTerima' => $g->TanggalTerima->toDateString(),
                'TanggalJatuhTempo' => $g->TanggalJatuhTempo->toDateString(),
                'Jumlah' => (string) $g->Jumlah,
                'Status' => $g->Status->value,
                'LabelStatus' => $g->Status->AmbilLabel(),
                'TanggalCair' => $g->TanggalCair?->toDateString(),
                'AlasanTolak' => $g->AlasanTolak,
                'JurnalCair' => $tautan($g->IdJurnalCair),
            ])->all());
        });

        return $hasil + ['Ringkasan' => $this->Ringkasan()];
    }

    /**
     * @return array{MasukJumlah: int, MasukNilai: string, KeluarJumlah: int, KeluarNilai: string}
     */
    private function Ringkasan(): array
    {
        $hasil = ['MasukJumlah' => 0, 'MasukNilai' => '0.00', 'KeluarJumlah' => 0, 'KeluarNilai' => '0.00'];
        $baris = Giro::query()->where('Status', StatusGiro::Menunggu->value)->groupBy('Arah')->selectRaw('Arah, COUNT(*) AS Jumlah, SUM(Jumlah) AS Nilai')->toBase()->get();

        foreach ($baris as $b) {
            $kunci = (string) $b->Arah === ArahGiro::Masuk->value ? 'Masuk' : 'Keluar';
            $hasil[$kunci.'Jumlah'] = (int) $b->Jumlah;
            $hasil[$kunci.'Nilai'] = Uang::Dari((string) $b->Nilai)->KeString();
        }

        return $hasil;
    }
}
