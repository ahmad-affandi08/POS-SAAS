import type { ComponentProps } from 'react';
import { id as lokalId } from 'react-day-picker/locale';

import { Calendar, CalendarDayButton } from '@/Komponen/Ui/calendar';
import { cn } from '@/Komponen/Ui/utils';

type PropsKalender = ComponentProps<typeof Calendar>;

const namaBulan = new Intl.DateTimeFormat('id-ID', { month: 'long' });

/** Tombol hari: hover & rentang tengah memakai brand lembut, angka tabular. */
function HariKalender({ className, ...sisa }: ComponentProps<typeof CalendarDayButton>) {
    return (
        <CalendarDayButton
            className={cn(
                'text-isi tabular-nums hover:bg-brand-lembut data-[range-middle=true]:bg-brand-lembut data-[range-middle=true]:text-teks-utama',
                className,
            )}
            {...sisa}
        />
    );
}

/**
 * Kalender PAYOU (§17.6): bahasa Indonesia, minggu dimulai Senin, pilihan bulan & tahun untuk lompat jauh
 * (mis. tanggal kedaluwarsa), hari ini ditandai garis bawah, pilihan memakai warna brand, rentang tengah
 * memakai brand lembut. Ukuran sel 40px (44px pada layar sentuh) sesuai target sentuh §17.4.4.
 */
export default function Kalender({ className, classNames, components, ...props }: PropsKalender) {
    return (
        <Calendar
            locale={lokalId}
            weekStartsOn={1}
            captionLayout="dropdown"
            startMonth={new Date(2000, 0)}
            endMonth={new Date(new Date().getFullYear() + 15, 11)}
            formatters={{ formatMonthDropdown: (tanggal) => namaBulan.format(tanggal) }}
            className={cn(
                'bg-transparent p-0 [--cell-size:--spacing(10)] pointer-coarse:[--cell-size:--spacing(11)]',
                className,
            )}
            classNames={{
                months: 'relative flex flex-col gap-6 sm:flex-row',
                month_caption: 'flex h-(--cell-size) w-full items-center justify-center px-(--cell-size)',
                dropdowns: 'flex h-(--cell-size) w-full items-center justify-center gap-1.5 text-isi font-semibold',
                dropdown_root:
                    'relative rounded-kontrol border border-garis-input has-focus:border-brand has-focus:ring-2 has-focus:ring-brand/40',
                caption_label:
                    'flex h-8 items-center gap-1 rounded-kontrol pr-1 pl-2 text-isi font-semibold text-teks-utama select-none [&>svg]:size-3.5 [&>svg]:text-teks-sekunder',
                weekday: 'flex-1 text-keterangan font-semibold text-teks-sekunder select-none',
                today: 'font-semibold [&_button]:underline [&_button]:decoration-2 [&_button]:underline-offset-4',
                outside: 'text-teks-sekunder/60 aria-selected:text-teks-sekunder',
                disabled: 'text-teks-sekunder/40 [&_button]:cursor-not-allowed',
                range_start: 'rounded-l-kontrol bg-brand-lembut',
                range_middle: 'rounded-none',
                range_end: 'rounded-r-kontrol bg-brand-lembut',
                ...classNames,
            }}
            components={{
                DayButton: HariKalender,
                ...components,
            }}
            {...props}
        />
    );
}
