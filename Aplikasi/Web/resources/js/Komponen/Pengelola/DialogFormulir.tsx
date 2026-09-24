import type { ReactNode } from 'react';

import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Komponen/Ui/dialog';
import { cn } from '@/Komponen/Ui/utils';

type PropsDialogFormulir = {
    judul: string;
    deskripsi?: ReactNode;
    saatTutup: () => void;
    lebar?: 'sedang' | 'lebar';
    children: ReactNode;
    /** Galat umum (`errors.Umum`) dari pengiriman terakhir dialog ini; halaman di belakang tertutup tirai. */
    galatUmum?: string | undefined;
};

/**
 * Bingkai modal untuk formulir tambah/ubah data master Platform Pengelola. Dialog selalu terbuka selama
 * dirender; menutup (Esc, klik tirai) memanggil `saatTutup` sama seperti tombol Batal.
 */
export default function DialogFormulir({
    judul,
    deskripsi,
    saatTutup,
    lebar = 'sedang',
    children,
    galatUmum,
}: PropsDialogFormulir) {
    return (
        <Dialog
            open
            onOpenChange={(terbuka) => {
                if (!terbuka) {
                    saatTutup();
                }
            }}
        >
            <DialogContent
                showCloseButton={false}
                className={cn('max-h-[90vh] overflow-y-auto', lebar === 'lebar' ? 'sm:max-w-3xl' : 'sm:max-w-xl')}
                {...(deskripsi ? {} : { 'aria-describedby': undefined })}
            >
                <DialogHeader>
                    <DialogTitle className="text-subjudul text-teks-utama">{judul}</DialogTitle>
                    {deskripsi ? <DialogDescription>{deskripsi}</DialogDescription> : null}
                </DialogHeader>
                {galatUmum ? <Pemberitahuan jenis="bahaya">{galatUmum}</Pemberitahuan> : null}
                {children}
            </DialogContent>
        </Dialog>
    );
}
