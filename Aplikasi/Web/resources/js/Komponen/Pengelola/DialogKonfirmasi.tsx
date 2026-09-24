import type { ReactNode } from 'react';

import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Komponen/Ui/alert-dialog';

type PropsDialogKonfirmasi = {
    judul: string;
    deskripsi: ReactNode;
    saatTutup: () => void;
    /** Tombol keputusan (terbitkan, setujui, tolak, hapus). Tidak menutup dialog sendiri; tutup lewat `onSuccess`. */
    aksi: ReactNode;
    /** Isian tambahan di antara deskripsi dan tombol, misal catatan peninjau. */
    children?: ReactNode;
    /** Galat umum (`errors.Umum`) dari pengiriman terakhir dialog ini; halaman di belakang tertutup tirai. */
    galatUmum?: string | undefined;
    labelBatal?: string;
};

/**
 * Konfirmasi keputusan yang tidak bisa dibatalkan (terbit, setujui/tolak four-eyes, hapus draf).
 * Dialog selalu terbuka selama dirender; Esc dan tombol Batal memanggil `saatTutup`.
 */
export default function DialogKonfirmasi({
    judul,
    deskripsi,
    saatTutup,
    aksi,
    children,
    labelBatal = 'Batal',
    galatUmum,
}: PropsDialogKonfirmasi) {
    return (
        <AlertDialog
            open
            onOpenChange={(terbuka) => {
                if (!terbuka) {
                    saatTutup();
                }
            }}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-subjudul text-teks-utama">{judul}</AlertDialogTitle>
                    <AlertDialogDescription>{deskripsi}</AlertDialogDescription>
                </AlertDialogHeader>
                {galatUmum ? <Pemberitahuan jenis="bahaya">{galatUmum}</Pemberitahuan> : null}
                {children}
                <AlertDialogFooter>
                    {aksi}
                    <AlertDialogCancel>{labelBatal}</AlertDialogCancel>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
