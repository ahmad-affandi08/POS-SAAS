import type { ReactNode } from 'react';

import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Komponen/Ui/alert-dialog';
import { Button } from '@/Komponen/Ui/button';

type PropsDialogKonfirmasi = {
    judul: string;
    /** Akibat tindakan, ditulis konkret (§17.6.7). */
    children: ReactNode;
    labelAksi: string;
    /** `bahaya` untuk tindakan merusak (nonaktifkan, cabut, hapus); `utama` untuk tindakan final biasa. */
    varian?: 'bahaya' | 'utama';
    memproses?: boolean;
    saatKonfirmasi: () => void;
    saatBatal: () => void;
};

/**
 * Konfirmasi tindakan berisiko (AlertDialog). Terbuka selama dipasang; tidak tertutup saat klik di luar.
 * Tombol aksi tidak menutup dialog sendiri: induk menutupnya saat permintaan berhasil (`onSuccess`).
 */
export default function DialogKonfirmasi({
    judul,
    children,
    labelAksi,
    varian = 'bahaya',
    memproses = false,
    saatKonfirmasi,
    saatBatal,
}: PropsDialogKonfirmasi) {
    return (
        <AlertDialog
            open
            onOpenChange={(terbuka) => {
                if (!terbuka && !memproses) {
                    saatBatal();
                }
            }}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-subjudul font-semibold text-teks-utama">{judul}</AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="flex flex-col gap-2 text-isi text-teks-sekunder">{children}</div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={memproses}>Batal</AlertDialogCancel>
                    <Button
                        variant={varian === 'bahaya' ? 'destructive' : 'default'}
                        disabled={memproses}
                        aria-busy={memproses || undefined}
                        onClick={saatKonfirmasi}
                    >
                        {memproses ? 'Memproses…' : labelAksi}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
