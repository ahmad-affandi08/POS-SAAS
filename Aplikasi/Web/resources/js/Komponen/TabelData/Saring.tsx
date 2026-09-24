import { XIcon } from 'lucide-react';
import { useId } from 'react';

import { Button } from '@/Komponen/Ui/button';
import { Checkbox } from '@/Komponen/Ui/checkbox';
import { Input } from '@/Komponen/Ui/input';
import { Label } from '@/Komponen/Ui/label';
import { Switch } from '@/Komponen/Ui/switch';
import { cn } from '@/Komponen/Ui/utils';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';

import type { DefinisiSaring } from './Tipe';

function TulisTanggal(tanggal: Date): string {
    const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
    const hari = String(tanggal.getDate()).padStart(2, '0');

    return `${String(tanggal.getFullYear())}-${bulan}-${hari}`;
}

/** Preset rentang tanggal §17.6.5 (tanggal lokal peramban). */
export function BuatPresetTanggal(hariIni = new Date()): { label: string; nilai: string }[] {
    const tahun = hariIni.getFullYear();
    const bulan = hariIni.getMonth();
    const tujuhHari = new Date(tahun, bulan, hariIni.getDate() - 6);
    const kemarin = new Date(tahun, bulan, hariIni.getDate() - 1);

    return [
        { label: 'Hari ini', nilai: `${TulisTanggal(hariIni)}..${TulisTanggal(hariIni)}` },
        { label: 'Kemarin', nilai: `${TulisTanggal(kemarin)}..${TulisTanggal(kemarin)}` },
        { label: '7 hari terakhir', nilai: `${TulisTanggal(tujuhHari)}..${TulisTanggal(hariIni)}` },
        {
            label: 'Bulan ini',
            nilai: `${TulisTanggal(new Date(tahun, bulan, 1))}..${TulisTanggal(new Date(tahun, bulan + 1, 0))}`,
        },
        {
            label: 'Bulan lalu',
            nilai: `${TulisTanggal(new Date(tahun, bulan - 1, 1))}..${TulisTanggal(new Date(tahun, bulan, 0))}`,
        },
    ];
}

export function PecahRentang(nilai: string): [string, string] {
    const [dari = '', sampai = ''] = nilai.split('..');

    return [dari, sampai];
}

/** Teks ringkas nilai saring untuk chip & tombol. */
export function RingkasSaring(definisi: DefinisiSaring, nilai: string): string {
    if (definisi.jenis === 'ya') {
        return definisi.labelAktif ?? definisi.label;
    }

    if (definisi.jenis === 'rentangTanggal') {
        const [dari, sampai] = PecahRentang(nilai);
        const preset = BuatPresetTanggal().find((p) => p.nilai === nilai);

        if (preset) {
            return preset.label;
        }

        if (dari !== '' && sampai !== '') {
            return dari === sampai ? FormatTanggal(dari) : `${FormatTanggal(dari)} – ${FormatTanggal(sampai)}`;
        }

        return dari !== '' ? `sejak ${FormatTanggal(dari)}` : `sampai ${FormatTanggal(sampai)}`;
    }

    const label = new Map((definisi.opsi ?? []).map((o) => [o.nilai, o.label]));

    return nilai
        .split(',')
        .map((n) => label.get(n) ?? n)
        .join(', ');
}

type PropsPenyunting = { definisi: DefinisiSaring; nilai: string; saatBerubah: (nilai: string) => void };

/** Isi penyunting satu saring; dipakai di Popover (desktop) dan Sheet "Saring" (HP). */
export function PenyuntingSaring({ definisi, nilai, saatBerubah }: PropsPenyunting) {
    const id = useId();

    if (definisi.jenis === 'ya') {
        return (
            <div className="flex min-h-11 items-center justify-between gap-3">
                <Label htmlFor={id} className="text-isi text-teks-utama">
                    {definisi.labelAktif ?? definisi.label}
                </Label>
                <Switch id={id} checked={nilai === '1'} onCheckedChange={(aktif) => saatBerubah(aktif ? '1' : '')} />
            </div>
        );
    }

    if (definisi.jenis === 'rentangTanggal') {
        const [dari, sampai] = PecahRentang(nilai);
        const Tulis = (d: string, s: string) => saatBerubah(d === '' && s === '' ? '' : `${d}..${s}`);

        return (
            <div className="flex flex-col gap-3">
                <div className="flex flex-wrap gap-2" role="group" aria-label={`Preset ${definisi.label}`}>
                    {BuatPresetTanggal().map((preset) => (
                        <Button
                            key={preset.label}
                            type="button"
                            size="sm"
                            variant={preset.nilai === nilai ? 'default' : 'outline'}
                            aria-pressed={preset.nilai === nilai}
                            onClick={() => saatBerubah(preset.nilai)}
                            className="text-label"
                        >
                            {preset.label}
                        </Button>
                    ))}
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`${id}-dari`} className="text-label font-semibold text-teks-utama">
                            Dari
                        </Label>
                        <Input
                            id={`${id}-dari`}
                            type="date"
                            value={dari}
                            max={sampai || undefined}
                            onChange={(e) => Tulis(e.target.value, sampai)}
                            className="h-10 border-garis-input"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`${id}-sampai`} className="text-label font-semibold text-teks-utama">
                            Sampai
                        </Label>
                        <Input
                            id={`${id}-sampai`}
                            type="date"
                            value={sampai}
                            min={dari || undefined}
                            onChange={(e) => Tulis(dari, e.target.value)}
                            className="h-10 border-garis-input"
                        />
                    </div>
                </div>
            </div>
        );
    }

    const terpilih = new Set(nilai === '' ? [] : nilai.split(','));
    const banyak = definisi.jenis === 'pilihanBanyak';
    const Ganti = (opsi: string, aktif: boolean) => {
        if (!banyak) {
            saatBerubah(aktif ? opsi : '');

            return;
        }

        const baru = new Set(terpilih);

        if (aktif) {
            baru.add(opsi);
        } else {
            baru.delete(opsi);
        }

        // Urutan mengikuti daftar opsi agar URL stabil.
        saatBerubah(
            (definisi.opsi ?? [])
                .map((o) => o.nilai)
                .filter((n) => baru.has(n))
                .join(','),
        );
    };

    return (
        <fieldset className="flex max-h-72 flex-col gap-1 overflow-y-auto">
            <legend className="sr-only">{definisi.label}</legend>
            {(definisi.opsi ?? []).map((opsi) => {
                const idOpsi = `${id}-${opsi.nilai}`;

                return (
                    <div key={opsi.nilai} className="flex min-h-10 items-center gap-2 rounded-kontrol px-1">
                        <Checkbox
                            id={idOpsi}
                            checked={terpilih.has(opsi.nilai)}
                            onCheckedChange={(aktif) => Ganti(opsi.nilai, aktif === true)}
                            className={cn(!banyak && 'rounded-full')}
                        />
                        <Label htmlFor={idOpsi} className="flex-1 text-isi font-normal text-teks-utama">
                            {opsi.label}
                        </Label>
                    </div>
                );
            })}
        </fieldset>
    );
}

type PropsChip = { definisi: DefinisiSaring; nilai: string; saatHapus: () => void };

/** Chip saring aktif dengan tombol hapus. */
export function ChipSaring({ definisi, nilai, saatHapus }: PropsChip) {
    const ringkas = RingkasSaring(definisi, nilai);
    const teks = definisi.jenis === 'ya' ? ringkas : `${definisi.label}: ${ringkas}`;

    return (
        <span className="inline-flex max-w-full items-center gap-1 rounded-kontrol border border-garis bg-brand-lembut py-0.5 pr-0.5 pl-2 text-label text-teks-utama">
            <span className="truncate">{teks}</span>
            <button
                type="button"
                onClick={saatHapus}
                aria-label={`Hapus saring ${teks}`}
                className="inline-flex size-7 shrink-0 items-center justify-center rounded-kontrol text-teks-sekunder hover:bg-brand-lembut-sorot hover:text-teks-utama focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none"
            >
                <XIcon aria-hidden="true" className="size-4" />
            </button>
        </span>
    );
}
