import { router, usePage } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { useId, useState, type FormEvent } from 'react';

import { GalatBidang, KerangkaBidang, KeteranganBidang } from '@/Komponen/Formulir/BagianBidang';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DialogFormulir from '@/Komponen/Tindakan/DialogFormulir';
import { Input } from '@/Komponen/Ui/input';
import { cn } from '@/Komponen/Ui/utils';
import { FormatUkuranBerkas } from '@/Pustaka/FormatUkuran';

import type { GambarPustaka } from './Tipe';

export const TIPE_GAMBAR = '.jpg,.jpeg,.png,.webp';
export const UKURAN_GAMBAR_MAKS_KB = 3072;

type PropsFormUnggah = { saatTerunggah?: (uuid: string) => void };

/** Unggah gambar ke pustaka situs; `saatTerunggah` menerima Uuid gambar baru. */
export function FormUnggahGambar({ saatTerunggah }: PropsFormUnggah) {
    const { props } = usePage<{ Gambar: GambarPustaka[]; errors: Record<string, string> }>();
    const idBerkas = useId();
    const [berkas, AturBerkas] = useState<File | null>(null);
    const [alt, AturAlt] = useState('');
    const [memproses, AturMemproses] = useState(false);
    const [kunciMasukan, AturKunciMasukan] = useState(0);

    const Kirim = (p: FormEvent) => {
        p.preventDefault();

        if (berkas === null) {
            return;
        }

        const sebelum = new Set(props.Gambar.map((g) => g.Uuid));
        router.post(
            '/situs/gambar',
            { Berkas: berkas, TeksAlternatif: alt },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onSuccess: (halaman) => {
                    const daftar = (halaman.props as unknown as { Gambar: GambarPustaka[] }).Gambar;
                    const baru = daftar.find((g) => !sebelum.has(g.Uuid));
                    AturBerkas(null);
                    AturAlt('');
                    AturKunciMasukan((k) => k + 1);

                    if (baru && saatTerunggah) {
                        saatTerunggah(baru.Uuid);
                    }
                },
            },
        );
    };

    return (
        <form onSubmit={Kirim} className="grid gap-3 rounded-panel border border-garis p-4 sm:grid-cols-2" noValidate>
            <KerangkaBidang galat={props.errors.Berkas}>
                <label htmlFor={idBerkas} className="text-label font-semibold text-teks-utama">
                    Unggah gambar baru
                </label>
                <Input
                    key={kunciMasukan}
                    id={idBerkas}
                    type="file"
                    accept={TIPE_GAMBAR}
                    onChange={(p) => AturBerkas(p.target.files?.[0] ?? null)}
                    aria-invalid={props.errors.Berkas ? true : undefined}
                    className="bg-permukaan text-isi"
                />
                <KeteranganBidang>
                    JPG, PNG, atau WEBP, maksimal {FormatUkuranBerkas(UKURAN_GAMBAR_MAKS_KB * 1024)}. Pakai lebar
                    ±1600px untuk gambar utama.
                </KeteranganBidang>
                {props.errors.Berkas ? <GalatBidang>{props.errors.Berkas}</GalatBidang> : null}
            </KerangkaBidang>
            <BidangTeks
                label="Teks alternatif"
                keterangan="Jelaskan isi gambar untuk pembaca layar & mesin pencari."
                nilai={alt}
                saatBerubah={AturAlt}
                galat={props.errors.TeksAlternatif}
                maxLength={150}
            />
            <div className="sm:col-span-2">
                <Tombol type="submit" memproses={memproses} disabled={berkas === null}>
                    Unggah
                </Tombol>
            </div>
        </form>
    );
}

type PropsPemilihGambar = {
    label: string;
    nilai: string | null;
    saatBerubah: (uuid: string | null) => void;
    galat?: string | undefined;
    keterangan?: string;
    bolehUbah: boolean;
};

/** Pilih gambar dari pustaka situs (atau unggah baru) untuk satu bidang gambar. */
export default function PemilihGambarSitus({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    bolehUbah,
}: PropsPemilihGambar) {
    const { props } = usePage<{ Gambar: GambarPustaka[] }>();
    const [terbuka, AturTerbuka] = useState(false);
    const terpilih = props.Gambar.find((g) => g.Uuid === nilai) ?? null;
    const Pilih = (uuid: string) => {
        saatBerubah(uuid);
        AturTerbuka(false);
    };

    return (
        <KerangkaBidang galat={galat}>
            <span className="text-label font-semibold text-teks-utama">{label}</span>
            <div className="flex items-center gap-3">
                <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-kontrol border border-garis bg-latar">
                    {terpilih ? (
                        <img
                            src={terpilih.Url}
                            alt={terpilih.TeksAlternatif ?? ''}
                            className="size-full object-cover"
                        />
                    ) : nilai ? (
                        <span className="px-1 text-center text-keterangan text-bahaya">Gambar hilang</span>
                    ) : (
                        <ImageIcon className="size-6 text-teks-sekunder" aria-hidden />
                    )}
                </div>
                <div className="flex min-w-0 flex-col gap-1">
                    <span className="truncate text-isi text-teks-utama">
                        {terpilih ? terpilih.NamaBerkas : 'Belum ada gambar'}
                    </span>
                    {bolehUbah ? (
                        <div className="flex flex-wrap gap-2">
                            <Tombol varian="sekunder" onClick={() => AturTerbuka(true)}>
                                {nilai ? 'Ganti' : 'Pilih gambar'}
                            </Tombol>
                            {nilai ? (
                                <Tombol varian="sekunder" onClick={() => saatBerubah(null)}>
                                    Kosongkan
                                </Tombol>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
            {keterangan ? <KeteranganBidang>{keterangan}</KeteranganBidang> : null}
            {galat ? <GalatBidang>{galat}</GalatBidang> : null}
            {terbuka ? (
                <DialogFormulir judul={`Pilih gambar: ${label}`} lebar="lebar" saatTutup={() => AturTerbuka(false)}>
                    <div className="flex flex-col gap-4">
                        <FormUnggahGambar saatTerunggah={Pilih} />
                        {props.Gambar.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">
                                Pustaka masih kosong. Unggah gambar pertama di atas.
                            </p>
                        ) : (
                            <ul className="grid max-h-[50vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                                {props.Gambar.map((g) => (
                                    <li key={g.Uuid}>
                                        <button
                                            type="button"
                                            onClick={() => Pilih(g.Uuid)}
                                            aria-pressed={g.Uuid === nilai}
                                            className={cn(
                                                'flex w-full flex-col gap-1 rounded-kontrol border p-1 text-left outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                                g.Uuid === nilai
                                                    ? 'border-2 border-brand'
                                                    : 'border-garis hover:border-garis-input',
                                            )}
                                        >
                                            <img
                                                src={g.Url}
                                                alt={g.TeksAlternatif ?? ''}
                                                loading="lazy"
                                                className="aspect-video w-full rounded-kontrol bg-latar object-contain"
                                            />
                                            <span className="truncate px-1 text-keterangan text-teks-sekunder">
                                                {g.NamaBerkas}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </DialogFormulir>
            ) : null}
        </KerangkaBidang>
    );
}
