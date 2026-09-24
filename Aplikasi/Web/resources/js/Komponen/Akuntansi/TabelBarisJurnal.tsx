import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Komponen/Ui/table';
import { cn } from '@/Komponen/Ui/utils';
import { FormatRupiah } from '@/Pustaka/Format';
import type { PropsDetailJurnal } from '@/Tipe/Akuntansi';

type PropsTabelBarisJurnal = {
    baris: PropsDetailJurnal['Baris'];
    total: PropsDetailJurnal['Total'];
    nomor: string;
};

const kelasKepala = 'h-auto px-4 py-2 text-label font-semibold text-teks-sekunder';
const kelasUang = 'px-4 text-right whitespace-nowrap tabular-nums';

/** Nilai satu sisi; sisi nol ditampilkan kosong agar sisi yang terisi mudah dibaca. */
function SelUang({ nilai }: { nilai: string }) {
    return /^0+(\.0+)?$/.test(nilai) ? <span className="sr-only">nol</span> : <>{FormatRupiah(nilai)}</>;
}

/**
 * Baris jurnal (akun, outlet, debit, kredit, memo) dengan total di kaki tabel (DesainF05a E). Debit dulu lalu kredit
 * sesuai urutan simpan. Uang dari server berupa string desimal, tidak pernah diubah ke number (CLAUDE.md #7).
 */
export default function TabelBarisJurnal({ baris, total, nomor }: PropsTabelBarisJurnal) {
    const seimbang = total.Debit === total.Kredit;

    return (
        <section className="rounded-panel border border-garis bg-card">
            <Table className="min-w-[720px] text-left text-isi">
                <TableCaption className="sr-only">Baris jurnal {nomor}</TableCaption>
                <TableHeader>
                    <TableRow className="border-garis hover:bg-transparent">
                        <TableHead scope="col" className={kelasKepala}>
                            Akun
                        </TableHead>
                        <TableHead scope="col" className={kelasKepala}>
                            Outlet
                        </TableHead>
                        <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                            Debit
                        </TableHead>
                        <TableHead scope="col" className={cn(kelasKepala, 'text-right')}>
                            Kredit
                        </TableHead>
                        <TableHead scope="col" className={kelasKepala}>
                            Memo
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {baris.map((satu) => {
                        const kredit = !/^0+(\.0+)?$/.test(satu.Kredit);

                        return (
                            <TableRow key={satu.Urutan} className="border-garis align-top">
                                <TableCell className={cn('px-4 whitespace-normal', kredit && 'pl-10')}>
                                    <span className="font-mono text-label text-teks-sekunder">{satu.KodeAkun}</span>{' '}
                                    <span className="break-words text-teks-utama">{satu.NamaAkun}</span>
                                </TableCell>
                                <TableCell className="px-4 whitespace-normal text-teks-sekunder">
                                    {satu.NamaOutlet ?? 'Tingkat usaha'}
                                </TableCell>
                                <TableCell className={kelasUang}>
                                    <SelUang nilai={satu.Debit} />
                                </TableCell>
                                <TableCell className={kelasUang}>
                                    <SelUang nilai={satu.Kredit} />
                                </TableCell>
                                <TableCell className="px-4 whitespace-normal text-keterangan text-teks-sekunder">
                                    {satu.Memo ?? ''}
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
                <TableFooter className="border-garis bg-permukaan-redup">
                    <TableRow className="hover:bg-transparent">
                        <TableCell colSpan={2} className="px-4 font-semibold text-teks-utama">
                            Total {seimbang ? '(seimbang)' : '(tidak seimbang)'}
                        </TableCell>
                        <TableCell className={cn(kelasUang, 'font-semibold')}>{FormatRupiah(total.Debit)}</TableCell>
                        <TableCell className={cn(kelasUang, 'font-semibold')}>{FormatRupiah(total.Kredit)}</TableCell>
                        <TableCell className="px-4" />
                    </TableRow>
                </TableFooter>
            </Table>
        </section>
    );
}
