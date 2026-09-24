import { CalendarIcon, XIcon } from 'lucide-react';
import { useId, useState, type KeyboardEvent } from 'react';

import { Button } from '@/Komponen/Ui/button';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/Komponen/Ui/input-group';
import { Label } from '@/Komponen/Ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Komponen/Ui/popover';
import { cn } from '@/Komponen/Ui/utils';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { CekDiLuarBatas, FormatTanggalIsian, TulisTanggal, UraiTanggal, UraiTeksTanggal } from '@/Pustaka/Tanggal';

import Kalender from './Kalender';

export type PropsPemilihTanggal = {
    label: string;
    /** `TTTT-BB-HH` atau string kosong. */
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string | undefined;
    disabled?: boolean;
    required?: boolean;
    /** Batas bawah/atas `TTTT-BB-HH` (inklusif). Hari di luar batas tidak bisa dipilih di kalender. */
    min?: string | undefined;
    max?: string | undefined;
    id?: string;
    className?: string;
    /** Sembunyikan tombol X (mis. di panel rentang yang sudah punya tombol Kosongkan). */
    tanpaKosongkan?: boolean;
    /** Untuk `PemilihTanggalWaktu`: tempelan `TTTT-BB-HHTjj:mm` diteruskan utuh ke `saatBerubah`. */
    terimaTanggalWaktu?: boolean;
};

/** Pesan galat lokal ketikan (sebelum dikirim ke server). */
function PeriksaKetikan(teks: string, min?: string, max?: string): string | null {
    if (teks.trim() === '') {
        return null;
    }

    const iso = UraiTeksTanggal(teks);

    if (iso === undefined) {
        return 'Tulis tanggal sebagai HH/BB/TTTT, misal 24/09/2026.';
    }

    if (min && iso < min) {
        return `Tanggal paling awal ${FormatTanggal(min)}.`;
    }

    if (max && iso > max) {
        return `Tanggal paling akhir ${FormatTanggal(max)}.`;
    }

    return null;
}

/**
 * Pemilih satu tanggal (§17.6): isian `HH/BB/TTTT` yang bisa diketik cepat + kalender di popover.
 * Nilai keluar selalu `TTTT-BB-HH` (atau kosong) dan hanya dikirim bila tanggalnya sah dan dalam batas.
 * Alt+↓ membuka kalender dari keyboard.
 */
export default function PemilihTanggal({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    disabled = false,
    required = false,
    min,
    max,
    id,
    className,
    terimaTanggalWaktu = false,
    tanpaKosongkan = false,
}: PropsPemilihTanggal) {
    const idOtomatis = useId();
    const idBidang = id ?? idOtomatis;
    const idKeterangan = `${idBidang}-keterangan`;
    const idGalat = `${idBidang}-galat`;
    const [terbuka, AturTerbuka] = useState(false);
    const [teks, AturTeks] = useState(() => FormatTanggalIsian(nilai));
    const [nilaiTerakhir, AturNilaiTerakhir] = useState(nilai);
    const [galatKetikan, AturGalatKetikan] = useState<string | null>(null);

    // Nilai dari luar berubah (reset form, pilih preset): tampilkan ulang dalam format isian.
    if (nilai !== nilaiTerakhir) {
        AturNilaiTerakhir(nilai);
        if (UraiTeksTanggal(teks) !== nilai) {
            AturTeks(FormatTanggalIsian(nilai));
        }
    }

    const terpilih = UraiTanggal(nilai);
    const hariIni = TulisTanggal(new Date());
    const galatTampil = galat ?? galatKetikan ?? undefined;
    const dijelaskanOleh = [keterangan ? idKeterangan : null, galatTampil ? idGalat : null].filter(Boolean).join(' ');
    const batasKalender = [
        ...(min && UraiTanggal(min) ? [{ before: UraiTanggal(min) as Date }] : []),
        ...(max && UraiTanggal(max) ? [{ after: UraiTanggal(max) as Date }] : []),
    ];

    const Terapkan = (baru: string) => {
        AturTeks(FormatTanggalIsian(baru));
        AturGalatKetikan(null);
        AturNilaiTerakhir(baru);
        saatBerubah(baru);
    };

    const SaatKetik = (isian: string) => {
        if (terimaTanggalWaktu && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(isian.trim())) {
            saatBerubah(isian.trim());

            return;
        }

        AturTeks(isian);
        const iso = UraiTeksTanggal(isian);

        if (isian.trim() === '') {
            AturGalatKetikan(null);
            AturNilaiTerakhir('');
            saatBerubah('');
        } else if (iso !== undefined && !CekDiLuarBatas(iso, min, max)) {
            AturGalatKetikan(null);
            AturNilaiTerakhir(iso);
            saatBerubah(iso);
        }
    };

    const SaatKeluar = () => {
        const pesan = PeriksaKetikan(teks, min, max);
        AturGalatKetikan(pesan);

        if (pesan === null && teks.trim() !== '') {
            AturTeks(FormatTanggalIsian(UraiTeksTanggal(teks) ?? ''));
        }
    };

    const SaatTombol = (peristiwa: KeyboardEvent<HTMLInputElement>) => {
        if (peristiwa.altKey && peristiwa.key === 'ArrowDown') {
            peristiwa.preventDefault();
            AturTerbuka(true);
        }
    };

    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <Label htmlFor={idBidang} className="text-label font-semibold text-teks-utama">
                {label}
            </Label>
            <InputGroup className="h-10 border-garis-input bg-permukaan pointer-coarse:h-11">
                <InputGroupInput
                    id={idBidang}
                    value={teks}
                    inputMode="numeric"
                    autoComplete="off"
                    placeholder="HH/BB/TTTT"
                    onChange={(peristiwa) => SaatKetik(peristiwa.target.value)}
                    onBlur={SaatKeluar}
                    onKeyDown={SaatTombol}
                    aria-invalid={galatTampil ? true : undefined}
                    aria-describedby={dijelaskanOleh || undefined}
                    aria-required={required || undefined}
                    disabled={disabled}
                    className="text-isi tabular-nums placeholder:text-teks-sekunder/70"
                />
                <InputGroupAddon align="inline-end">
                    {!required && !tanpaKosongkan && teks !== '' && !disabled ? (
                        <InputGroupButton size="icon-xs" aria-label={`Kosongkan ${label}`} onClick={() => Terapkan('')}>
                            <XIcon />
                        </InputGroupButton>
                    ) : null}
                    <Popover open={terbuka} onOpenChange={AturTerbuka}>
                        <PopoverTrigger asChild>
                            <InputGroupButton
                                size="icon-xs"
                                aria-label={`Pilih ${label} dari kalender`}
                                disabled={disabled}
                                className="text-teks-sekunder hover:text-brand"
                            >
                                <CalendarIcon />
                            </InputGroupButton>
                        </PopoverTrigger>
                        <PopoverContent className="w-auto p-3" align="end">
                            <Kalender
                                mode="single"
                                required={false}
                                {...(terpilih ? { selected: terpilih, defaultMonth: terpilih } : {})}
                                disabled={batasKalender}
                                onSelect={(tanggal) => {
                                    if (tanggal) {
                                        Terapkan(TulisTanggal(tanggal));
                                    }
                                    AturTerbuka(false);
                                }}
                            />
                            <div className="mt-3 flex items-center justify-between gap-2 border-t border-garis pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={CekDiLuarBatas(hariIni, min, max)}
                                    onClick={() => {
                                        Terapkan(hariIni);
                                        AturTerbuka(false);
                                    }}
                                >
                                    Hari ini
                                </Button>
                                <span className="text-keterangan text-teks-sekunder">
                                    {terpilih ? FormatTanggal(nilai) : 'Belum dipilih'}
                                </span>
                            </div>
                        </PopoverContent>
                    </Popover>
                </InputGroupAddon>
            </InputGroup>
            {keterangan ? (
                <p id={idKeterangan} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            {galatTampil ? (
                <p id={idGalat} className="text-keterangan font-semibold text-bahaya">
                    {galatTampil}
                </p>
            ) : null}
        </div>
    );
}
