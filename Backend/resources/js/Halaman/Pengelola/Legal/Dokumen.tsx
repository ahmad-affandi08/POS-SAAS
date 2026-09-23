import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type DokumenLegal = {
    Uuid: string;
    Jenis: string;
    Label: string;
    Versi: number;
    Judul: string;
    Isi: string;
    RingkasanPerubahan: string | null;
    Materiil: boolean;
    BerlakuMulai: string;
    Status: 'Draf' | 'Terbit';
    DiterbitkanPada: string | null;
};

/** Satu versi dokumen legal: sunting draf atau baca versi terbit (P-06, BR-P06.1). */
export default function HalamanDokumenLegal({ Dokumen }: { Dokumen: DokumenLegal }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehUbah = Dokumen.Status === 'Draf' && PunyaIzin(props.Pengguna, IzinPengelola.LegalKelola);
    const url = `/legal/${Dokumen.Uuid}`;

    const [memproses, AturMemproses] = useState(false);
    const opsiKirim = {
        preserveScroll: true,
        onStart: () => AturMemproses(true),
        onFinish: () => AturMemproses(false),
    };
    const Hapus = () => {
        if (window.confirm(`Hapus draf ${Dokumen.Label} versi ${Dokumen.Versi}?`)) {
            router.delete(url, opsiKirim);
        }
    };

    return (
        <TataLetakPengelola
            judul={`${Dokumen.Label} · versi ${Dokumen.Versi}`}
            aksi={
                bolehUbah ? (
                    <Tombol varian="bahaya" memproses={memproses} onClick={Hapus}>
                        Hapus draf
                    </Tombol>
                ) : null
            }
        >
            <Link href="/legal" className="text-label font-semibold text-brand underline">
                Semua dokumen legal
            </Link>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            <div className="flex flex-wrap items-center gap-2 text-keterangan text-teks-sekunder">
                <LabelStatus jenis={Dokumen.Status === 'Draf' ? 'peringatan' : 'sukses'} teks={Dokumen.Status} />
                <span>Berlaku mulai {FormatTanggal(Dokumen.BerlakuMulai)}</span>
                {Dokumen.DiterbitkanPada ? <span>Terbit {FormatTanggalWaktu(Dokumen.DiterbitkanPada)}</span> : null}
            </div>
            {bolehUbah ? (
                <FormDraf dokumen={Dokumen} url={url} galatHalaman={props.errors} />
            ) : (
                <article className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-6">
                    <h2 className="text-subjudul font-semibold text-teks-utama">{Dokumen.Judul}</h2>
                    {Dokumen.RingkasanPerubahan ? (
                        <p className="text-keterangan text-teks-sekunder">Perubahan: {Dokumen.RingkasanPerubahan}</p>
                    ) : null}
                    <div className="whitespace-pre-wrap text-isi text-teks-utama">{Dokumen.Isi}</div>
                </article>
            )}
        </TataLetakPengelola>
    );
}

type PropsFormDraf = { dokumen: DokumenLegal; url: string; galatHalaman: Record<string, string> };

function FormDraf({ dokumen, url, galatHalaman }: PropsFormDraf) {
    const idIsi = useId();
    const [menerbitkan, AturMenerbitkan] = useState(false);
    const formulir = useForm({
        Jenis: dokumen.Jenis,
        Judul: dokumen.Judul,
        Isi: dokumen.Isi,
        RingkasanPerubahan: dokumen.RingkasanPerubahan ?? '',
        Materiil: dokumen.Materiil,
        BerlakuMulai: dokumen.BerlakuMulai,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(url, { preserveScroll: true });
    };
    // Terbitkan memakai versi tersimpan; perubahan yang belum disimpan harus disimpan dulu (BR-P06.1).
    const Terbitkan = () => {
        if (window.confirm(`Terbitkan ${dokumen.Label} versi ${dokumen.Versi}? Versi terbit tidak bisa diubah lagi.`)) {
            router.post(
                `${url}/terbitkan`,
                {},
                { preserveScroll: true, onStart: () => AturMenerbitkan(true), onFinish: () => AturMenerbitkan(false) },
            );
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <BidangTeks
                label="Judul"
                nilai={formulir.data.Judul}
                saatBerubah={(nilai) => formulir.setData('Judul', nilai)}
                galat={formulir.errors.Judul}
            />
            <BidangTeks
                label="Berlaku mulai (TTTT-BB-HH)"
                keterangan="Perubahan materiil paling cepat 30 hari setelah terbit."
                nilai={formulir.data.BerlakuMulai}
                saatBerubah={(nilai) => formulir.setData('BerlakuMulai', nilai)}
                galat={formulir.errors.BerlakuMulai ?? galatHalaman.BerlakuMulai}
            />
            <div className="sm:col-span-2">
                <KotakCentang
                    label="Perubahan materiil (mengubah hak atau kewajiban tenant)"
                    nilai={formulir.data.Materiil}
                    saatBerubah={(nilai) => formulir.setData('Materiil', nilai)}
                />
            </div>
            <div className="sm:col-span-2">
                <BidangTeks
                    label="Ringkasan perubahan (opsional)"
                    keterangan="Ditampilkan ke tenant saat versi ini diumumkan."
                    nilai={formulir.data.RingkasanPerubahan}
                    saatBerubah={(nilai) => formulir.setData('RingkasanPerubahan', nilai)}
                    galat={formulir.errors.RingkasanPerubahan}
                />
            </div>
            <div className="flex flex-col gap-1 sm:col-span-2">
                <label htmlFor={idIsi} className="text-label font-semibold text-teks-utama">
                    Isi dokumen (Markdown)
                </label>
                <textarea
                    id={idIsi}
                    rows={20}
                    value={formulir.data.Isi}
                    onChange={(peristiwa) => formulir.setData('Isi', peristiwa.target.value)}
                    aria-invalid={formulir.errors.Isi ? true : undefined}
                    className={`rounded-kontrol border bg-permukaan px-3 py-2 font-mono text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                        formulir.errors.Isi ? 'border-bahaya' : 'border-garis-input'
                    }`}
                />
                {formulir.errors.Isi ? (
                    <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.Isi}</p>
                ) : null}
            </div>
            <div className="flex flex-wrap items-center gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan draf
                </Tombol>
                <Tombol varian="sekunder" memproses={menerbitkan} disabled={formulir.isDirty} onClick={Terbitkan}>
                    Terbitkan
                </Tombol>
                {formulir.isDirty ? (
                    <span className="text-keterangan text-teks-sekunder">
                        Simpan perubahan dulu sebelum menerbitkan.
                    </span>
                ) : null}
            </div>
        </form>
    );
}
