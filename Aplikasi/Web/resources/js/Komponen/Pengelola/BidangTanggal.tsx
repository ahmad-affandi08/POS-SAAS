import { CalendarIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { id as lokalId } from 'react-day-picker/locale';

import { Calendar } from '@/Komponen/Ui/calendar';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/Komponen/Ui/input-group';
import { Label } from '@/Komponen/Ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Komponen/Ui/popover';

type PropsBidangTanggal = {
    label: string;
    /** Selalu string `TTTT-BB-HH` (atau isian bebas yang sedang diketik); tidak pernah diubah ke objek Date. */
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    disabled?: boolean;
};

const polaTanggal = /^(\d{4})-(\d{2})-(\d{2})$/;

/** `TTTT-BB-HH` → Date lokal (tanpa geser zona waktu), atau undefined bila belum lengkap/tidak valid. */
export function UraiTanggal(nilai: string): Date | undefined {
    const cocok = polaTanggal.exec(nilai.trim());

    if (cocok === null) {
        return undefined;
    }

    const [tahun, bulan, hari] = [Number(cocok[1]), Number(cocok[2]), Number(cocok[3])];
    const tanggal = new Date(tahun, bulan - 1, hari);

    return tanggal.getFullYear() === tahun && tanggal.getMonth() === bulan - 1 && tanggal.getDate() === hari
        ? tanggal
        : undefined;
}

/** Date lokal → `TTTT-BB-HH`. */
export function TulisTanggal(tanggal: Date): string {
    const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
    const hari = String(tanggal.getDate()).padStart(2, '0');

    return `${String(tanggal.getFullYear())}-${bulan}-${hari}`;
}

/**
 * Input tanggal teks `TTTT-BB-HH` dengan kalender pembantu. Nilai yang dikirim tetap string yang sama
 * seperti bila diketik manual; kalender hanya mengisi string tersebut.
 */
export default function BidangTanggal({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    disabled = false,
}: PropsBidangTanggal) {
    const idBidang = useId();
    const idKeterangan = `${idBidang}-keterangan`;
    const idGalat = `${idBidang}-galat`;
    const [terbuka, AturTerbuka] = useState(false);
    const terpilih = UraiTanggal(nilai);
    const dijelaskanOleh = [keterangan ? idKeterangan : null, galat ? idGalat : null].filter(Boolean).join(' ');

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={idBidang} className="text-label font-semibold text-teks-utama">
                {label}
            </Label>
            <InputGroup className="h-10">
                <InputGroupInput
                    id={idBidang}
                    value={nilai}
                    onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
                    aria-invalid={galat ? true : undefined}
                    aria-describedby={dijelaskanOleh || undefined}
                    disabled={disabled}
                    className="font-mono tracking-wide"
                />
                <InputGroupAddon align="inline-end">
                    <Popover open={terbuka} onOpenChange={AturTerbuka}>
                        <PopoverTrigger asChild>
                            <InputGroupButton
                                size="icon-xs"
                                aria-label={`Pilih ${label} dari kalender`}
                                disabled={disabled}
                            >
                                <CalendarIcon />
                            </InputGroupButton>
                        </PopoverTrigger>
                        <PopoverContent className="w-auto p-0" align="end">
                            <Calendar
                                mode="single"
                                locale={lokalId}
                                {...(terpilih ? { selected: terpilih, defaultMonth: terpilih } : {})}
                                onSelect={(tanggal) => {
                                    if (tanggal) {
                                        saatBerubah(TulisTanggal(tanggal));
                                    }
                                    AturTerbuka(false);
                                }}
                            />
                        </PopoverContent>
                    </Popover>
                </InputGroupAddon>
            </InputGroup>
            {keterangan ? (
                <p id={idKeterangan} className="text-keterangan text-teks-sekunder">
                    {keterangan}
                </p>
            ) : null}
            {galat ? (
                <p id={idGalat} className="text-keterangan font-semibold text-bahaya">
                    {galat}
                </p>
            ) : null}
        </div>
    );
}
