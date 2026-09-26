import { StoreIcon } from 'lucide-react';

import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Komponen/Ui/empty';
import TataLetakPanduanAwal from '@/TataLetak/TataLetakPanduanAwal';

/**
 * D-24: anggota selain pengelola panduan (misal kasir yang diundang) saat pemilik belum menyelesaikan panduan awal
 * tenant baru. Tidak ada yang bisa dikerjakan di back-office sampai toko siap.
 */
export default function HalamanMenungguPanduan() {
    return (
        <TataLetakPanduanAwal judul="Toko sedang disiapkan" wajib>
            <Empty className="rounded-panel border border-garis bg-permukaan px-4 py-6 md:p-8">
                <EmptyHeader>
                    <EmptyMedia variant="icon" aria-hidden="true">
                        <StoreIcon />
                    </EmptyMedia>
                    <EmptyTitle>Pemilik usaha belum selesai menyiapkan toko</EmptyTitle>
                    <EmptyDescription className="text-isi text-teks-sekunder">
                        Menu akan terbuka setelah panduan awal selesai. Minta pemilik usaha menyelesaikannya, lalu muat
                        ulang halaman ini.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </TataLetakPanduanAwal>
    );
}
