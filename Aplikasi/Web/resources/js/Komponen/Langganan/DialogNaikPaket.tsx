import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { FormatRupiah } from '@/Pustaka/Format';
import type { PenawaranFitur } from '@/Tipe/Aplikasi';

type PropsDialogNaikPaket = {
    kunci: string;
    penawaran: PenawaranFitur;
    namaPaket: string | null;
    /** Pemegang izin `langganan.kelola` (Pemilik) bisa langsung memilih paket atau meminta add-on. */
    bolehKelola: boolean;
    saatTutup: () => void;
};

/**
 * D-23: dialog ajakan untuk fitur di luar paket langganan (menu tetap tampil, seperti Majoo). Menawarkan paket termurah
 * yang memuat fitur itu (tautan ke Langganan dengan paket terpilih) dan add-on bila ada (permintaan menjadi tiket
 * dukungan sampai pembelian add-on mandiri tersedia). Anggota tanpa izin langganan diminta menghubungi Pemilik.
 */
export default function DialogNaikPaket({ kunci, penawaran, namaPaket, bolehKelola, saatTutup }: PropsDialogNaikPaket) {
    const [memproses, AturMemproses] = useState(false);
    const { Paket: paket, Addon: addon } = penawaran;
    const hargaPaket = paket?.HargaBulanan ? ` mulai ${FormatRupiah(paket.HargaBulanan)}/bulan` : '';

    return (
        <DialogFormulir
            judul={`${penawaran.Nama} belum termasuk paket ${namaPaket ?? 'Anda'}`}
            keterangan={
                <>
                    {paket ? (
                        <span>
                            Fitur ini tersedia di paket <strong>{paket.Nama}</strong>
                            {hargaPaket}.
                        </span>
                    ) : null}
                    {addon ? (
                        <span>
                            {paket ? 'Atau tambahkan' : 'Tambahkan'} add-on <strong>{addon.Nama}</strong> seharga{' '}
                            {FormatRupiah(addon.HargaBulanan)}/bulan tanpa ganti paket.
                        </span>
                    ) : null}
                    {!paket && !addon ? <span>Hubungi kami untuk mengaktifkan fitur ini.</span> : null}
                    {!bolehKelola ? <span>Minta Pemilik usaha untuk naik paket atau menambah add-on.</span> : null}
                </>
            }
            saatTutup={saatTutup}
        >
            <DialogFooter className="sm:justify-start">
                {bolehKelola && paket ? (
                    <Button asChild className="h-8 pointer-coarse:h-11">
                        <Link href={`/kelola/langganan?paket=${encodeURIComponent(paket.Kode)}`}>
                            Lihat paket {paket.Nama}
                        </Link>
                    </Button>
                ) : null}
                {bolehKelola && addon ? (
                    <Tombol
                        varian={paket ? 'sekunder' : 'utama'}
                        memproses={memproses}
                        onClick={() =>
                            router.post(
                                '/kelola/langganan/addon',
                                { KunciFitur: kunci },
                                { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
                            )
                        }
                    >
                        Minta add-on {addon.Nama}
                    </Tombol>
                ) : null}
                {bolehKelola && !paket && !addon ? (
                    <Button asChild variant="outline" className="h-8 pointer-coarse:h-11">
                        <Link href="/kelola/bantuan/buat">Hubungi kami</Link>
                    </Button>
                ) : null}
                <Tombol varian="sekunder" onClick={saatTutup}>
                    Nanti saja
                </Tombol>
            </DialogFooter>
        </DialogFormulir>
    );
}
