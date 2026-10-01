import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import BilahAksiForm from '@/Komponen/Formulir/BilahAksiForm';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import Panel from '@/Komponen/Kelola/Panel';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { KunciKueri } from '@/Pustaka/KunciKueri';
import { KirimJson } from '@/Pustaka/PermintaanJson';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { KanalKampanye, PratinjauKampanye, PropsFormKampanye, SegmenKampanye } from '@/Tipe/Kampanye';

import { AlamatKampanye } from './Daftar';

/** Tunda nilai yang sering berubah (pratinjau dihitung server, jangan tiap ketukan). */
function useTunda<T>(nilai: T, ms: number): T {
    const [tertunda, AturTertunda] = useState(nilai);

    useEffect(() => {
        const pewaktu = setTimeout(() => AturTertunda(nilai), ms);

        return () => clearTimeout(pewaktu);
    }, [nilai, ms]);

    return tertunda;
}

/**
 * CRM-07 formulir draf kampanye: kanal, segmen penerima (RFM, tier, tag, ulang tahun bulan ini), dan isi pesan dengan
 * `{nama}`/`{toko}`. Jumlah penerima dipratinjau server (hanya yang setuju promosi & punya kontak kanal itu).
 */
export default function HalamanFormKampanye({
    Kampanye,
    OpsiKanal,
    OpsiRfm,
    OpsiTier,
    OpsiTag,
    KanalAktif,
    MaksIsi,
}: PropsFormKampanye) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [nama, AturNama] = useState(Kampanye?.Nama ?? '');
    const [kanal, AturKanal] = useState<KanalKampanye>(Kampanye?.Kanal ?? (KanalAktif.Whatsapp ? 'Whatsapp' : 'Email'));
    const [judul, AturJudul] = useState(Kampanye?.Judul ?? '');
    const [isi, AturIsi] = useState(Kampanye?.Isi ?? 'Halo {nama}, ');
    const [rfm, AturRfm] = useState<string[]>(Kampanye?.Segmen.Rfm ?? []);
    const [tier, AturTier] = useState<string[]>(Kampanye?.Segmen.UuidTier ?? []);
    const [tag, AturTag] = useState<string[]>(Kampanye?.Segmen.Tag ?? []);
    const [ulangTahun, AturUlangTahun] = useState(Kampanye?.Segmen.UlangTahunBulanIni ?? false);
    const [memproses, AturMemproses] = useState(false);
    const segmen: SegmenKampanye = { Rfm: rfm, UuidTier: tier, Tag: tag, UlangTahunBulanIni: ulangTahun };
    const kunciSegmen = useTunda(JSON.stringify(segmen), 400);
    const judulHalaman = Kampanye ? `Ubah ${Kampanye.Nama}` : 'Buat kampanye';

    const pratinjau = useQuery({
        queryKey: KunciKueri.PratinjauKampanye(kanal, kunciSegmen),
        queryFn: () =>
            KirimJson<PratinjauKampanye>(`${AlamatKampanye}/pratinjau`, {
                Kanal: kanal,
                Segmen: JSON.parse(kunciSegmen) as SegmenKampanye,
            }),
        placeholderData: keepPreviousData,
    });

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const data = { Nama: nama, Kanal: kanal, Judul: kanal === 'Email' ? judul : null, Isi: isi, Segmen: segmen };
        const opsi = { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) };

        if (Kampanye) {
            router.put(`${AlamatKampanye}/${Kampanye.Uuid}`, data, opsi);
        } else {
            router.post(AlamatKampanye, data, opsi);
        }
    };

    return (
        <TataLetakAplikasi judul={judulHalaman} jejak={[{ label: 'Kampanye pesan', href: AlamatKampanye }]}>
            <DaftarGalatServer galat={galat} kecuali={['Nama', 'Kanal', 'Judul', 'Isi']} />
            <form onSubmit={Simpan} noValidate aria-label={judulHalaman} className="flex flex-col gap-4">
                <Panel judul="Kampanye" idJudul="judul-kampanye">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <BidangTeks
                            label="Nama kampanye"
                            nilai={nama}
                            saatBerubah={AturNama}
                            galat={galat.Nama}
                            required
                        />
                        <BidangPilihan
                            label="Kanal"
                            nilai={kanal}
                            opsi={OpsiKanal.map((o) => ({
                                Nilai: o.Nilai,
                                Label: KanalAktif[o.Nilai as KanalKampanye] ? o.Label : `${o.Label} (belum aktif)`,
                            }))}
                            saatBerubah={(nilai) => AturKanal(nilai as KanalKampanye)}
                            galat={galat.Kanal}
                            required
                        />
                    </div>
                    {!KanalAktif[kanal] ? (
                        <Pemberitahuan jenis="peringatan">
                            {kanal === 'Whatsapp'
                                ? 'Pengiriman WhatsApp belum aktif untuk usaha ini. Draf tetap bisa disimpan, tetapi belum bisa dikirim.'
                                : 'Pengiriman email belum aktif. Draf tetap bisa disimpan, tetapi belum bisa dikirim.'}
                        </Pemberitahuan>
                    ) : null}
                </Panel>

                <Panel
                    judul="Penerima"
                    idJudul="judul-penerima-kampanye"
                    keterangan="Hanya pelanggan aktif yang menyetujui kabar promosi. Saringan yang dikosongkan berarti semua."
                >
                    <GrupCentang
                        legenda="Segmen belanja (RFM)"
                        opsi={OpsiRfm.map((o) => ({
                            nilai: o.Nilai,
                            label: `${o.Label} (${String(pratinjau.data?.PerSegmen[o.Nilai as keyof PratinjauKampanye['PerSegmen']] ?? '…')})`,
                        }))}
                        terpilih={rfm}
                        saatBerubah={AturRfm}
                    />
                    <ul className="grid gap-1 text-keterangan text-teks-sekunder sm:grid-cols-2">
                        {OpsiRfm.map((o) => (
                            <li key={o.Nilai}>
                                <span className="font-semibold text-teks-utama">{o.Label}:</span> {o.Keterangan}
                            </li>
                        ))}
                    </ul>
                    {OpsiTier.length > 0 ? (
                        <GrupCentang
                            legenda="Tier"
                            opsi={OpsiTier.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                            terpilih={tier}
                            saatBerubah={AturTier}
                        />
                    ) : null}
                    {OpsiTag.length > 0 ? (
                        <GrupCentang
                            legenda="Tag (salah satu)"
                            opsi={OpsiTag.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                            terpilih={tag}
                            saatBerubah={AturTag}
                        />
                    ) : null}
                    <KotakCentang
                        label="Hanya yang berulang tahun bulan ini"
                        nilai={ulangTahun}
                        saatBerubah={AturUlangTahun}
                    />
                    <div role="status" aria-live="polite" className="text-isi text-teks-utama">
                        {pratinjau.isError ? (
                            <span className="text-bahaya">Jumlah penerima belum bisa dihitung. Coba lagi.</span>
                        ) : pratinjau.data ? (
                            <>
                                <span className="font-semibold tabular-nums">
                                    {String(pratinjau.data.JumlahPenerima)} pelanggan
                                </span>{' '}
                                akan menerima pesan ini
                                {pratinjau.data.TanpaKontak > 0
                                    ? ` (${String(pratinjau.data.TanpaKontak)} lainnya cocok tetapi tidak punya ${kanal === 'Whatsapp' ? 'nomor WhatsApp' : 'email'} yang sah).`
                                    : '.'}
                                {pratinjau.data.JumlahPenerima > pratinjau.data.MaksPenerima
                                    ? ` Paling banyak ${String(pratinjau.data.MaksPenerima)} per kampanye; persempit segmennya.`
                                    : null}
                            </>
                        ) : (
                            'Menghitung penerima…'
                        )}
                    </div>
                </Panel>

                <Panel judul="Isi pesan" idJudul="judul-isi-kampanye">
                    {kanal === 'Email' ? (
                        <BidangTeks
                            label="Judul email"
                            nilai={judul}
                            saatBerubah={AturJudul}
                            galat={galat.Judul}
                            required
                        />
                    ) : null}
                    <BidangTeksPanjang
                        label="Isi pesan"
                        nilai={isi}
                        saatBerubah={AturIsi}
                        galat={galat.Isi}
                        baris={6}
                        maksimal={MaksIsi}
                        keterangan="Tulis {nama} untuk nama pelanggan dan {toko} untuk nama usaha. Nama usaha dan tautan berhenti berlangganan ditambahkan otomatis di akhir pesan."
                        required
                    />
                </Panel>

                <BilahAksiForm>
                    <Tombol type="submit" memproses={memproses}>
                        Simpan draf
                    </Tombol>
                    <Tombol varian="sekunder" onClick={() => router.visit(AlamatKampanye)}>
                        Batal
                    </Tombol>
                </BilahAksiForm>
            </form>
        </TataLetakAplikasi>
    );
}
