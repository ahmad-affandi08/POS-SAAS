import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import PercakapanTiket, { type PesanTiket } from '@/Komponen/Dukungan/PercakapanTiket';
import { jenisLabelStatusTiket, type BatasLampiran, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import BidangBerkas from '@/Komponen/Formulir/BidangBerkas';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type DetailTiket = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    LabelKategori: string;
    LabelPrioritas: string;
    Status: StatusTiket;
    LabelStatus: string;
    DibuatPada: string;
    BisaDibalas: boolean;
    BisaDiselesaikan: boolean;
    Pesan: PesanTiket[];
};

/** Percakapan tiket bantuan tenant: balas, lampirkan berkas, tandai selesai (P-09). */
export default function TiketBantuan({ Tiket, Lampiran }: { Tiket: DetailTiket; Lampiran: BatasLampiran }) {
    const formulir = useForm<{ Isi: string; Lampiran: File[] }>({ Isi: '', Lampiran: [] });
    const [menyelesaikan, AturMenyelesaikan] = useState(false);
    const galatLampiran = Object.entries(formulir.errors).find(([kunci]) => kunci.startsWith('Lampiran'))?.[1];
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/kelola/bantuan/${Tiket.Uuid}/balasan`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => formulir.reset(),
        });
    };
    const Selesaikan = () =>
        router.post(
            `/kelola/bantuan/${Tiket.Uuid}/selesaikan`,
            {},
            { preserveScroll: true, onStart: () => AturMenyelesaikan(true), onFinish: () => AturMenyelesaikan(false) },
        );

    return (
        <TataLetakAplikasi judul={Tiket.Judul}>
            <div className="flex flex-wrap items-center gap-2 text-label text-teks-sekunder">
                <Link href="/kelola/bantuan" className="font-semibold text-brand underline">
                    Semua tiket
                </Link>
                <span aria-hidden="true">/</span>
                <span className="font-mono">{Tiket.Nomor}</span>
                <LabelStatus jenis={jenisLabelStatusTiket[Tiket.Status]} teks={Tiket.LabelStatus} />
                <span>
                    {Tiket.LabelKategori} · Prioritas {Tiket.LabelPrioritas} · Dibuat{' '}
                    {FormatTanggalWaktu(Tiket.DibuatPada)}
                </span>
            </div>

            <PercakapanTiket
                pesan={Tiket.Pesan}
                tautanLampiran={(uuid) => `/kelola/bantuan/${Tiket.Uuid}/lampiran/${uuid}`}
            />

            {Tiket.BisaDibalas ? (
                <form
                    onSubmit={Kirim}
                    className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-5"
                >
                    {Tiket.Status === 'Selesai' ? (
                        <Pemberitahuan jenis="info">Mengirim balasan akan membuka lagi tiket ini.</Pemberitahuan>
                    ) : null}
                    <BidangTeksPanjang
                        label="Balasan Anda"
                        nilai={formulir.data.Isi}
                        maxLength={10000}
                        saatBerubah={(nilai) => formulir.setData('Isi', nilai)}
                        galat={formulir.errors.Isi}
                    />
                    <BidangBerkas
                        label="Lampiran"
                        berkas={formulir.data.Lampiran}
                        ekstensi={Lampiran.Ekstensi}
                        maksimal={Lampiran.Maksimal}
                        ukuranMaksimalKb={Lampiran.UkuranMaksimalKb}
                        saatBerubah={(berkas) => formulir.setData('Lampiran', berkas)}
                        galat={galatLampiran}
                    />
                    <div className="flex flex-wrap gap-3">
                        <Tombol type="submit" memproses={formulir.processing}>
                            Kirim balasan
                        </Tombol>
                        {Tiket.BisaDiselesaikan ? (
                            <Tombol varian="sekunder" memproses={menyelesaikan} onClick={Selesaikan}>
                                Masalah sudah selesai
                            </Tombol>
                        ) : null}
                    </div>
                </form>
            ) : (
                <Pemberitahuan jenis="info" judul="Tiket ditutup">
                    Tiket ini tidak bisa dibalas lagi. Bila masalahnya muncul lagi,{' '}
                    <Link href="/kelola/bantuan/buat" className="font-semibold text-brand underline">
                        buat tiket baru
                    </Link>{' '}
                    dan sebutkan nomor {Tiket.Nomor}.
                </Pemberitahuan>
            )}
        </TataLetakAplikasi>
    );
}
