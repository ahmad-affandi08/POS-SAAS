import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import type { BatasLampiran } from '@/Komponen/Dukungan/StatusTiket';
import BidangBerkas from '@/Komponen/Formulir/BidangBerkas';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type PropsBuat = {
    Kategori: { Nilai: string; Label: string }[];
    Prioritas: { Nilai: string; Label: string; Keterangan: string }[];
    Lampiran: BatasLampiran;
};

type IsianTiket = {
    Kategori: string;
    Prioritas: string;
    Judul: string;
    Isi: string;
    Lampiran: File[];
};

/** Formulir tiket bantuan baru (P-09). */
export default function BuatTiketBantuan({ Kategori, Prioritas, Lampiran }: PropsBuat) {
    const formulir = useForm<IsianTiket>({ Kategori: '', Prioritas: 'Normal', Judul: '', Isi: '', Lampiran: [] });
    const galatLampiran = Object.entries(formulir.errors).find(([kunci]) => kunci.startsWith('Lampiran'))?.[1];
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/kelola/bantuan', { forceFormData: true });
    };

    return (
        <TataLetakAplikasi judul="Buat tiket bantuan">
            <Card className="max-w-2xl p-5">
                <form onSubmit={Kirim} className="flex flex-col gap-4">
                    <BidangPilihan
                        label="Kategori"
                        nilai={formulir.data.Kategori}
                        opsi={Kategori}
                        kosong="Pilih kategori masalah"
                        saatBerubah={(nilai) => formulir.setData('Kategori', nilai)}
                        galat={formulir.errors.Kategori}
                    />
                    <BidangPilihan
                        label="Seberapa mendesak?"
                        nilai={formulir.data.Prioritas}
                        opsi={Prioritas.map((pilihan) => ({
                            Nilai: pilihan.Nilai,
                            Label: `${pilihan.Label}: ${pilihan.Keterangan}`,
                        }))}
                        saatBerubah={(nilai) => formulir.setData('Prioritas', nilai)}
                        galat={formulir.errors.Prioritas}
                    />
                    <BidangTeks
                        label="Judul"
                        nilai={formulir.data.Judul}
                        maxLength={150}
                        keterangan="Ringkas masalahnya, misal: Printer struk Outlet Kemang tidak mencetak."
                        saatBerubah={(nilai) => formulir.setData('Judul', nilai)}
                        galat={formulir.errors.Judul}
                    />
                    <BidangTeksPanjang
                        label="Uraian masalah"
                        nilai={formulir.data.Isi}
                        baris={7}
                        maksimal={10000}
                        keterangan="Tulis apa yang terjadi, sejak kapan, di outlet/perangkat mana, dan apa yang sudah dicoba."
                        saatBerubah={(nilai) => formulir.setData('Isi', nilai)}
                        galat={formulir.errors.Isi}
                    />
                    <BidangBerkas
                        label="Lampiran (foto layar, dokumen)"
                        berkas={formulir.data.Lampiran}
                        ekstensi={Lampiran.Ekstensi}
                        maksimal={Lampiran.Maksimal}
                        ukuranMaksimalKb={Lampiran.UkuranMaksimalKb}
                        saatBerubah={(berkas) => formulir.setData('Lampiran', berkas)}
                        galat={galatLampiran}
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <Tombol type="submit" memproses={formulir.processing}>
                            Kirim tiket
                        </Tombol>
                        <Button asChild variant="outline">
                            <Link href="/kelola/bantuan">Batal</Link>
                        </Button>
                    </div>
                </form>
            </Card>
        </TataLetakAplikasi>
    );
}
