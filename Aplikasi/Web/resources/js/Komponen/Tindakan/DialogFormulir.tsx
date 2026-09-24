import type { ReactNode } from 'react';

import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Komponen/Ui/alert-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Komponen/Ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Komponen/Ui/sheet';

type PropsDialogFormulir = {
    judul: string;
    keterangan?: ReactNode;
    /**
     * - `dialog`: formulir pendek di tengah layar.
     * - `panel`: formulir panjang (banyak centang) di panel samping.
     * - `konfirmasi`: tindakan berisiko; AlertDialog, tidak tertutup saat klik di luar.
     */
    jenis?: 'dialog' | 'panel' | 'konfirmasi';
    saatTutup: () => void;
    children: ReactNode;
};

const kelasJudul = 'text-subjudul font-semibold text-teks-utama';
const kelasKeterangan = 'flex flex-col gap-2 text-isi text-teks-sekunder';

/**
 * Wadah formulir tindakan. Dirender (terbuka) selama induknya memasangnya; menutup lewat Esc/Batal
 * memanggil `saatTutup`. Tombol kirim & batal tetap milik formulir di dalamnya.
 */
export default function DialogFormulir({
    judul,
    keterangan,
    jenis = 'dialog',
    saatTutup,
    children,
}: PropsDialogFormulir) {
    const UbahTerbuka = (terbuka: boolean) => {
        if (!terbuka) {
            saatTutup();
        }
    };
    const denganKeterangan = keterangan !== undefined && keterangan !== null;

    if (jenis === 'konfirmasi') {
        return (
            <AlertDialog open onOpenChange={UbahTerbuka}>
                <AlertDialogContent
                    className="max-h-[90vh] overflow-y-auto"
                    {...(denganKeterangan ? {} : { 'aria-describedby': undefined })}
                >
                    <AlertDialogHeader>
                        <AlertDialogTitle className={kelasJudul}>{judul}</AlertDialogTitle>
                        {denganKeterangan ? (
                            <AlertDialogDescription asChild>
                                <div className={kelasKeterangan}>{keterangan}</div>
                            </AlertDialogDescription>
                        ) : null}
                    </AlertDialogHeader>
                    {children}
                </AlertDialogContent>
            </AlertDialog>
        );
    }

    if (jenis === 'panel') {
        return (
            <Sheet open onOpenChange={UbahTerbuka}>
                <SheetContent
                    side="right"
                    className="w-full overflow-y-auto sm:max-w-xl"
                    {...(denganKeterangan ? {} : { 'aria-describedby': undefined })}
                >
                    <SheetHeader>
                        <SheetTitle className={kelasJudul}>{judul}</SheetTitle>
                        {denganKeterangan ? (
                            <SheetDescription asChild>
                                <div className={kelasKeterangan}>{keterangan}</div>
                            </SheetDescription>
                        ) : null}
                    </SheetHeader>
                    <div className="px-4 pb-4">{children}</div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <Dialog open onOpenChange={UbahTerbuka}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-xl"
                {...(denganKeterangan ? {} : { 'aria-describedby': undefined })}
            >
                <DialogHeader>
                    <DialogTitle className={kelasJudul}>{judul}</DialogTitle>
                    {denganKeterangan ? (
                        <DialogDescription asChild>
                            <div className={kelasKeterangan}>{keterangan}</div>
                        </DialogDescription>
                    ) : null}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}
