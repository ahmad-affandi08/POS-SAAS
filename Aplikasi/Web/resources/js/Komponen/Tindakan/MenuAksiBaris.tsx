import { MoreHorizontalIcon } from 'lucide-react';

import { Button } from '@/Komponen/Ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Komponen/Ui/dropdown-menu';

export type AksiBaris = {
    label: string;
    saatPilih: () => void;
    /** Aksi merusak/berisiko (nonaktifkan, cabut, hapus): dipisah di bawah & diberi warna bahaya. */
    bahaya?: boolean;
    nonaktif?: boolean;
};

type PropsMenuAksiBaris = {
    /** Nama aksesibel tombol pemicu, sebut objeknya, misal "Aksi untuk Rina Wulandari". */
    label: string;
    aksi: AksiBaris[];
};

/**
 * Isi menu aksi (`DropdownMenuItem`): aksi biasa di atas, aksi bahaya dipisah di bawah. Dipakai `MenuAksiBaris` dan
 * `aksiBaris` milik `TabelData`.
 */
export function ItemAksiBaris({ aksi }: { aksi: AksiBaris[] }) {
    const aksiBiasa = aksi.filter((item) => item.bahaya !== true);
    const aksiBahaya = aksi.filter((item) => item.bahaya === true);

    return (
        <>
            {aksiBiasa.map((item) => (
                <DropdownMenuItem key={item.label} disabled={item.nonaktif === true} onSelect={item.saatPilih}>
                    {item.label}
                </DropdownMenuItem>
            ))}
            {aksiBiasa.length > 0 && aksiBahaya.length > 0 ? <DropdownMenuSeparator /> : null}
            {aksiBahaya.map((item) => (
                <DropdownMenuItem
                    key={item.label}
                    variant="destructive"
                    disabled={item.nonaktif === true}
                    onSelect={item.saatPilih}
                >
                    {item.label}
                </DropdownMenuItem>
            ))}
        </>
    );
}

/** Menu aksi per baris tabel/daftar (§17.6). Tidak dirender bila tidak ada aksi yang boleh. */
export default function MenuAksiBaris({ label, aksi }: PropsMenuAksiBaris) {
    if (aksi.length === 0) {
        return null;
    }

    return (
        // Non-modal: dialog yang dibuka dari menu tidak berebut fokus & pointer-events dengan menu.
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={label}>
                    <MoreHorizontalIcon aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <ItemAksiBaris aksi={aksi} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
