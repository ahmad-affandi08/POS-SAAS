import { ChartNoAxesColumnIcon } from 'lucide-react';

import DaftarLangkahBerikutnya from '@/Komponen/Kelola/DaftarLangkahBerikutnya';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia } from '@/Komponen/Ui/empty';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBerandaKelola } from '@/Tipe/PanduanAwal';

/** Beranda back-office: checklist "Langkah berikutnya" (F-01 langkah 7). Dasbor penjualan dibangun F-14. */
export default function HalamanBerandaKelola({ LangkahBerikutnya }: PropsBerandaKelola) {
    return (
        <TataLetakAplikasi judul="Beranda">
            <DaftarLangkahBerikutnya daftar={LangkahBerikutnya} />
            {LangkahBerikutnya.length === 0 ? (
                <Empty className="rounded-panel border border-garis bg-permukaan px-4 py-6 md:p-8">
                    <EmptyHeader>
                        <EmptyMedia variant="icon" aria-hidden="true">
                            <ChartNoAxesColumnIcon />
                        </EmptyMedia>
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            Belum ada ringkasan untuk ditampilkan. Ringkasan penjualan muncul di sini setelah outlet
                            mulai berjualan.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : null}
        </TataLetakAplikasi>
    );
}
