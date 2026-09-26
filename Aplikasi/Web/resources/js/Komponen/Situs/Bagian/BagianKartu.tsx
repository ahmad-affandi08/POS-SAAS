import IkonSitus from '@/Komponen/Situs/IkonSitus';
import TautanSitus from '@/Komponen/Situs/TautanSitus';
import type { BagianSitus } from '@/Tipe/Situs';

import { GambarBagian, KepalaBagian, WadahBagian } from './KepalaBagian';

const KOLOM = {
    '2': 'sm:grid-cols-2',
    '3': 'sm:grid-cols-2 lg:grid-cols-3',
    '4': 'sm:grid-cols-2 lg:grid-cols-4',
} as const;

type LatarBagian = 'latar' | 'permukaan';

/** Kartu ikon keunggulan/fitur. */
export function BagianKeunggulan({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'Keunggulan' }>;
    latar: LatarBagian;
}) {
    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            <ul className={`grid gap-4 ${KOLOM[bagian.Kolom ?? '3']}`}>
                {bagian.Item.map((item, i) => (
                    <li
                        key={`${item.Judul}-${i}`}
                        className={`flex flex-col gap-3 rounded-panel border border-garis p-6 ${
                            latar === 'latar' ? 'bg-permukaan' : 'bg-latar'
                        }`}
                    >
                        {item.Ikon ? (
                            <span className="inline-flex size-12 items-center justify-center rounded-panel bg-brand-lembut text-brand">
                                <IkonSitus nama={item.Ikon} />
                            </span>
                        ) : null}
                        <h3 className="text-subjudul font-semibold text-teks-utama">{item.Judul}</h3>
                        {item.Teks ? (
                            <p className="text-isi whitespace-pre-line text-teks-sekunder">{item.Teks}</p>
                        ) : null}
                    </li>
                ))}
            </ul>
        </WadahBagian>
    );
}

/** Kartu jenis usaha, bisa bertautan ke halaman solusi. */
export function BagianSektor({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'Sektor' }>;
    latar: LatarBagian;
}) {
    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {bagian.Item.map((item, i) => {
                    const isi = (
                        <>
                            {item.Gambar ? (
                                <GambarBagian
                                    gambar={item.Gambar}
                                    className="aspect-video w-full rounded-kontrol object-cover"
                                />
                            ) : null}
                            <div className="flex items-center gap-3">
                                {item.Ikon ? (
                                    <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-panel bg-brand-lembut text-brand">
                                        <IkonSitus nama={item.Ikon} className="size-5" />
                                    </span>
                                ) : null}
                                <h3 className="text-subjudul font-semibold text-teks-utama">{item.Nama}</h3>
                            </div>
                            {item.Teks ? (
                                <p className="text-isi whitespace-pre-line text-teks-sekunder">{item.Teks}</p>
                            ) : null}
                            {item.Tautan ? (
                                <span className="mt-auto text-isi font-semibold text-brand">Selengkapnya →</span>
                            ) : null}
                        </>
                    );
                    const kelas = `flex h-full flex-col gap-3 rounded-panel border border-garis p-6 ${
                        latar === 'latar' ? 'bg-permukaan' : 'bg-latar'
                    }`;

                    return (
                        <li key={`${item.Nama}-${i}`}>
                            {item.Tautan ? (
                                <TautanSitus href={item.Tautan} className={`${kelas} hover:border-brand`}>
                                    {isi}
                                </TautanSitus>
                            ) : (
                                <div className={kelas}>{isi}</div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </WadahBagian>
    );
}

/** Angka statistik; isinya ditulis pengelola (jangan mengarang angka, D-21). */
export function BagianStatistik({ bagian }: { bagian: Extract<BagianSitus, { Jenis: 'Statistik' }> }) {
    return (
        <WadahBagian latar="merek">
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} gelap />
            <dl className="grid grid-cols-2 gap-6 lg:grid-cols-[repeat(auto-fit,minmax(10rem,1fr))]">
                {bagian.Item.map((item, i) => (
                    <div key={`${item.Angka}-${i}`} className="flex flex-col-reverse gap-1 text-center">
                        <dt className="text-isi text-brand-gelap-teks">{item.Keterangan}</dt>
                        <dd className="text-judul-bagian-hp font-bold text-permukaan sm:text-judul-bagian">
                            {item.Angka}
                        </dd>
                    </div>
                ))}
            </dl>
        </WadahBagian>
    );
}

/** Testimoni pelanggan nyata yang dimasukkan pengelola. */
export function BagianTestimoni({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'Testimoni' }>;
    latar: LatarBagian;
}) {
    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {bagian.Item.map((item, i) => (
                    <li
                        key={`${item.Nama}-${i}`}
                        className={`flex flex-col gap-4 rounded-panel border border-garis p-6 ${
                            latar === 'latar' ? 'bg-permukaan' : 'bg-latar'
                        }`}
                    >
                        {item.Bintang ? (
                            <p className="text-subjudul text-peringatan" aria-label={`${item.Bintang} dari 5 bintang`}>
                                {'★'.repeat(item.Bintang)}
                                <span className="text-garis">{'★'.repeat(5 - item.Bintang)}</span>
                            </p>
                        ) : null}
                        <blockquote className="text-isi whitespace-pre-line text-teks-utama">
                            “{item.Kutipan}”
                        </blockquote>
                        <div className="mt-auto flex items-center gap-3">
                            {item.Foto ? (
                                <GambarBagian gambar={item.Foto} className="size-11 rounded-full object-cover" />
                            ) : null}
                            <div>
                                <p className="text-isi font-semibold text-teks-utama">{item.Nama}</p>
                                {item.Usaha ? <p className="text-label text-teks-sekunder">{item.Usaha}</p> : null}
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
        </WadahBagian>
    );
}

/** Logo mitra/klien. */
export function BagianLogoMitra({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'LogoMitra' }>;
    latar: LatarBagian;
}) {
    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            <ul className="flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
                {bagian.Item.map((item, i) => {
                    const logo = item.Gambar ? (
                        <GambarBagian gambar={{ ...item.Gambar, Alt: item.Nama }} className="h-10 w-auto sm:h-12" />
                    ) : (
                        <span className="text-subjudul font-semibold text-teks-sekunder">{item.Nama}</span>
                    );

                    return (
                        <li key={`${item.Nama}-${i}`}>
                            {item.Tautan ? <TautanSitus href={item.Tautan}>{logo}</TautanSitus> : logo}
                        </li>
                    );
                })}
            </ul>
        </WadahBagian>
    );
}
