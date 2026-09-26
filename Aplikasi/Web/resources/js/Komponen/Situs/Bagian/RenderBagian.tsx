import type { BagianSitus } from '@/Tipe/Situs';

import BagianHarga from './BagianHarga';
import BagianHero from './BagianHero';
import { BagianKeunggulan, BagianLogoMitra, BagianSektor, BagianStatistik, BagianTestimoni } from './BagianKartu';
import {
    BagianCta,
    BagianFaq,
    BagianGambarTeks,
    BagianKontak,
    BagianTeksBebas,
    BagianUnduhAplikasi,
    BagianVideo,
} from './BagianLain';

/** Blok berlatar sendiri (tidak ikut selang-seling latar). */
const LATAR_SENDIRI = new Set<BagianSitus['Jenis']>(['Hero', 'Statistik', 'Cta']);

/** Latar tiap blok: berselang-seling, dan mulai ulang setelah blok berlatar sendiri. */
export function HitungLatar(bagian: BagianSitus[]): ('latar' | 'permukaan')[] {
    let urutan = 0;

    return bagian.map((b) => {
        if (LATAR_SENDIRI.has(b.Jenis)) {
            urutan = 0;

            return 'latar';
        }

        const latar = urutan % 2 === 0 ? 'latar' : 'permukaan';
        urutan += 1;

        return latar;
    });
}

/**
 * Render daftar blok halaman situs (D-21). Latar blok berselang-seling (latar/permukaan) agar batas bagian jelas tanpa
 * garis atau bayangan hias. Blok Hero pertama memakai `<h1>`.
 */
export default function RenderBagian({ bagian }: { bagian: BagianSitus[] }) {
    const daftarLatar = HitungLatar(bagian);

    return (
        <>
            {bagian.map((b, i) => {
                const kunci = `${b.Jenis}-${i}`;
                const latar = daftarLatar[i] ?? 'latar';

                switch (b.Jenis) {
                    case 'Hero':
                        return <BagianHero key={kunci} bagian={b} utama={i === 0} />;
                    case 'Keunggulan':
                        return <BagianKeunggulan key={kunci} bagian={b} latar={latar} />;
                    case 'Sektor':
                        return <BagianSektor key={kunci} bagian={b} latar={latar} />;
                    case 'GambarTeks':
                        return <BagianGambarTeks key={kunci} bagian={b} latar={latar} />;
                    case 'Statistik':
                        return <BagianStatistik key={kunci} bagian={b} />;
                    case 'Testimoni':
                        return <BagianTestimoni key={kunci} bagian={b} latar={latar} />;
                    case 'Harga':
                        return <BagianHarga key={kunci} bagian={b} latar={latar} />;
                    case 'Faq':
                        return <BagianFaq key={kunci} bagian={b} latar={latar} />;
                    case 'Cta':
                        return <BagianCta key={kunci} bagian={b} />;
                    case 'TeksBebas':
                        return <BagianTeksBebas key={kunci} bagian={b} latar={latar} />;
                    case 'LogoMitra':
                        return <BagianLogoMitra key={kunci} bagian={b} latar={latar} />;
                    case 'Video':
                        return <BagianVideo key={kunci} bagian={b} latar={latar} />;
                    case 'UnduhAplikasi':
                        return <BagianUnduhAplikasi key={kunci} bagian={b} latar={latar} />;
                    case 'Kontak':
                        return <BagianKontak key={kunci} bagian={b} latar={latar} />;
                    default:
                        return null;
                }
            })}
        </>
    );
}
