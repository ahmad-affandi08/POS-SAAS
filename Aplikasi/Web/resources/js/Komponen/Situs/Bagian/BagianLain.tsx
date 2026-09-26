import { usePage } from '@inertiajs/react';
import { Check, ChevronDown, Clock, Mail, MapPin, MessageCircle, Phone } from 'lucide-react';

import TeksKaya from '@/Komponen/Situs/TeksKaya';
import TombolSitus from '@/Komponen/Situs/TombolSitus';
import type { BagianSitus, DataSitus } from '@/Tipe/Situs';

import { GambarBagian, KepalaBagian, WadahBagian } from './KepalaBagian';

type LatarBagian = 'latar' | 'permukaan';

/** Gambar di satu sisi, teks + poin + tombol di sisi lain. */
export function BagianGambarTeks({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'GambarTeks' }>;
    latar: LatarBagian;
}) {
    const gambarKiri = bagian.PosisiGambar === 'Kiri';

    return (
        <WadahBagian latar={latar}>
            <div className={`grid items-center gap-10 ${bagian.Gambar ? 'lg:grid-cols-2' : 'max-w-3xl'}`}>
                <div className={`flex flex-col gap-4 ${gambarKiri ? 'lg:order-2' : ''}`}>
                    <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} rata="kiri" />
                    {bagian.Teks ? (
                        <TeksKaya teks={bagian.Teks} className="-mt-6 text-subjudul text-teks-sekunder" />
                    ) : null}
                    {bagian.Poin.length > 0 ? (
                        <ul className="flex flex-col gap-3">
                            {bagian.Poin.map((poin, i) => (
                                <li key={`${poin.Teks}-${i}`} className="flex gap-3 text-subjudul text-teks-utama">
                                    <Check className="mt-1 size-5 shrink-0 text-sukses" aria-hidden />
                                    <span>{poin.Teks}</span>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    {bagian.Tombol ? (
                        <TombolSitus href={bagian.Tombol.Tautan} className="self-start">
                            {bagian.Tombol.Label}
                        </TombolSitus>
                    ) : null}
                </div>
                {bagian.Gambar ? (
                    <GambarBagian gambar={bagian.Gambar} className="h-auto w-full rounded-panel border border-garis" />
                ) : null}
            </div>
        </WadahBagian>
    );
}

/** Tanya jawab dengan `<details>` bawaan (bisa dibuka tanpa JavaScript, terbaca mesin pencari). */
export function BagianFaq({ bagian, latar }: { bagian: Extract<BagianSitus, { Jenis: 'Faq' }>; latar: LatarBagian }) {
    return (
        <WadahBagian latar={latar} id="faq" sempit>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            <div className="flex flex-col gap-3">
                {bagian.Item.map((item, i) => (
                    <details
                        key={`${item.Pertanyaan}-${i}`}
                        className={`group rounded-panel border border-garis ${latar === 'latar' ? 'bg-permukaan' : 'bg-latar'}`}
                    >
                        <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-subjudul font-semibold text-teks-utama [&::-webkit-details-marker]:hidden">
                            {item.Pertanyaan}
                            <ChevronDown
                                className="size-5 shrink-0 transition-transform group-open:rotate-180"
                                aria-hidden
                            />
                        </summary>
                        <TeksKaya teks={item.Jawaban} className="px-5 pb-5 text-isi text-teks-sekunder" />
                    </details>
                ))}
            </div>
        </WadahBagian>
    );
}

/** Ajakan penutup berlatar merek. */
export function BagianCta({ bagian }: { bagian: Extract<BagianSitus, { Jenis: 'Cta' }> }) {
    return (
        <WadahBagian latar="merek">
            <div className="mx-auto flex max-w-3xl flex-col items-center gap-5 text-center">
                <h2 className="text-judul-bagian-hp font-bold text-permukaan sm:text-judul-bagian">{bagian.Judul}</h2>
                {bagian.Teks ? (
                    <p className="text-pengantar whitespace-pre-line text-brand-gelap-teks">{bagian.Teks}</p>
                ) : null}
                <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    {bagian.TombolUtama ? (
                        <TombolSitus href={bagian.TombolUtama.Tautan} ukuran="besar" varian="terang">
                            {bagian.TombolUtama.Label}
                        </TombolSitus>
                    ) : null}
                    {bagian.TombolKedua ? (
                        <TombolSitus href={bagian.TombolKedua.Tautan} ukuran="besar" varian="garis-terang">
                            {bagian.TombolKedua.Label}
                        </TombolSitus>
                    ) : null}
                </div>
            </div>
        </WadahBagian>
    );
}

/** Teks panjang (tentang kami, kebijakan singkat, artikel sederhana). */
export function BagianTeksBebas({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'TeksBebas' }>;
    latar: LatarBagian;
}) {
    return (
        <WadahBagian latar={latar} sempit>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} rata="kiri" />
            <TeksKaya teks={bagian.Isi} className="text-subjudul text-teks-utama" />
        </WadahBagian>
    );
}

/** Video YouTube lewat youtube-nocookie (tanpa cookie pelacak sampai diputar). */
export function BagianVideo({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'Video' }>;
    latar: LatarBagian;
}) {
    if (!bagian.IdYoutube) {
        return null;
    }

    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            <div className="mx-auto aspect-video w-full max-w-4xl overflow-hidden rounded-panel border border-garis bg-teks-utama">
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${bagian.IdYoutube}`}
                    title={bagian.Judul ?? 'Video'}
                    loading="lazy"
                    allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    referrerPolicy="strict-origin-when-cross-origin"
                    className="size-full"
                />
            </div>
        </WadahBagian>
    );
}

const PLATFORM_UNDUH = [
    { Kunci: 'Android', Label: 'Unduh di Google Play', Keterangan: 'Android 8 ke atas' },
    { Kunci: 'Ios', Label: 'Unduh di App Store', Keterangan: 'iPhone & iPad' },
    { Kunci: 'Windows', Label: 'Unduh untuk Windows', Keterangan: 'Windows 10 ke atas' },
] as const;

/** Tautan unduh aplikasi dari pengaturan situs; platform tanpa tautan tidak ditampilkan. */
export function BagianUnduhAplikasi({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'UnduhAplikasi' }>;
    latar: LatarBagian;
}) {
    const { props } = usePage<{ Situs: DataSitus }>();
    const tersedia = PLATFORM_UNDUH.filter((p) => props.Situs.TautanUnduh[p.Kunci]);

    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            {tersedia.length === 0 ? (
                <p className="text-center text-isi text-teks-sekunder">
                    Tautan unduhan segera tersedia. Hubungi kami untuk mendapatkan aplikasinya.
                </p>
            ) : (
                <ul className="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    {tersedia.map((p) => (
                        <li key={p.Kunci} className="flex flex-col items-center gap-1">
                            <TombolSitus
                                href={props.Situs.TautanUnduh[p.Kunci] ?? '#'}
                                ukuran="besar"
                                className="w-full sm:w-auto"
                            >
                                {p.Label}
                            </TombolSitus>
                            <span className="text-label text-teks-sekunder">{p.Keterangan}</span>
                        </li>
                    ))}
                </ul>
            )}
        </WadahBagian>
    );
}

/** Kartu kontak dari pengaturan situs. */
export function BagianKontak({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'Kontak' }>;
    latar: LatarBagian;
}) {
    const { props } = usePage<{ Situs: DataSitus }>();
    const k = props.Situs.Kontak;
    const kartu = `flex gap-4 rounded-panel border border-garis p-6 ${latar === 'latar' ? 'bg-permukaan' : 'bg-latar'}`;
    const ada = k.TautanWhatsApp || k.Email || k.Telepon || k.Alamat;

    return (
        <WadahBagian latar={latar}>
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            {!ada ? (
                <p className="text-center text-isi text-teks-sekunder">Kontak sedang disiapkan.</p>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2">
                    {k.TautanWhatsApp ? (
                        <li className={kartu}>
                            <MessageCircle className="size-6 shrink-0 text-sukses" aria-hidden />
                            <div className="flex flex-col gap-2">
                                <h3 className="text-subjudul font-semibold text-teks-utama">WhatsApp</h3>
                                {k.WhatsApp ? <p className="text-isi text-teks-sekunder">{k.WhatsApp}</p> : null}
                                <TombolSitus href={k.TautanWhatsApp} className="self-start">
                                    Chat sekarang
                                </TombolSitus>
                            </div>
                        </li>
                    ) : null}
                    {k.Email ? (
                        <li className={kartu}>
                            <Mail className="size-6 shrink-0 text-brand" aria-hidden />
                            <div className="flex flex-col gap-1">
                                <h3 className="text-subjudul font-semibold text-teks-utama">Email</h3>
                                <a
                                    href={`mailto:${k.Email}`}
                                    className="text-isi font-semibold break-all text-brand underline"
                                >
                                    {k.Email}
                                </a>
                            </div>
                        </li>
                    ) : null}
                    {k.Telepon ? (
                        <li className={kartu}>
                            <Phone className="size-6 shrink-0 text-brand" aria-hidden />
                            <div className="flex flex-col gap-1">
                                <h3 className="text-subjudul font-semibold text-teks-utama">Telepon</h3>
                                <a
                                    href={`tel:${k.Telepon.replace(/[^\d+]/g, '')}`}
                                    className="text-isi font-semibold text-brand underline"
                                >
                                    {k.Telepon}
                                </a>
                            </div>
                        </li>
                    ) : null}
                    {k.Alamat ? (
                        <li className={kartu}>
                            <MapPin className="size-6 shrink-0 text-brand" aria-hidden />
                            <div className="flex flex-col gap-1">
                                <h3 className="text-subjudul font-semibold text-teks-utama">Alamat</h3>
                                <p className="text-isi whitespace-pre-line text-teks-sekunder">{k.Alamat}</p>
                            </div>
                        </li>
                    ) : null}
                </ul>
            )}
            {k.JamLayanan ? (
                <p className="mt-6 flex items-center justify-center gap-2 text-isi text-teks-sekunder">
                    <Clock className="size-4" aria-hidden /> Jam layanan: {k.JamLayanan}
                </p>
            ) : null}
        </WadahBagian>
    );
}
