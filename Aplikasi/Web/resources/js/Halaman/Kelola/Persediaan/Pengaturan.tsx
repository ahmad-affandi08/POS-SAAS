import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import GrupRadio from '@/Komponen/Katalog/GrupRadio';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import DialogKonfirmasi from '@/Komponen/Tindakan/DialogKonfirmasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { MetodeHpp, PropsPengaturanPersediaan } from '@/Tipe/Persediaan';

const alamat = '/kelola/persediaan/pengaturan';

/** F-05a: pengaturan persediaan tenant: metode HPP (terkunci setelah ada mutasi, H-4) dan izin stok minus (BR-05.2). */
export default function HalamanPengaturanPersediaan({
    MetodeHpp: metodeAwal,
    StokBolehMinus,
    MetodeHppTerkunci,
    AlasanTerkunci,
    OpsiMetodeHpp,
}: PropsPengaturanPersediaan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const [metode, AturMetode] = useState<MetodeHpp>(metodeAwal);
    const [bolehMinus, AturBolehMinus] = useState(StokBolehMinus);
    const [konfirmasi, AturKonfirmasi] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const berubah = metode !== metodeAwal || bolehMinus !== StokBolehMinus;
    const labelMetode = OpsiMetodeHpp.find((opsi) => opsi.Nilai === metode)?.Label ?? metode;

    const KirimPengaturan = () =>
        router.put(
            alamat,
            { MetodeHpp: metode, StokBolehMinus: bolehMinus },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
                onSuccess: () => AturKonfirmasi(false),
            },
        );

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();

        if (metode !== metodeAwal) {
            AturKonfirmasi(true);

            return;
        }

        KirimPengaturan();
    };

    return (
        <TataLetakAplikasi judul="Pengaturan persediaan">
            <DaftarGalatServer galat={galat} kecuali={['MetodeHpp', 'StokBolehMinus']} />

            <form onSubmit={Simpan} aria-label="Pengaturan persediaan" className="flex flex-col gap-4">
                <PanelKatalog
                    judul="Metode HPP"
                    idJudul="judul-metode-hpp"
                    keterangan="Cara menghitung harga pokok barang yang keluar. Berlaku untuk semua outlet."
                >
                    {MetodeHppTerkunci ? (
                        <Pemberitahuan jenis="info" judul="Metode HPP sudah terkunci">
                            {AlasanTerkunci ??
                                'Sudah ada mutasi stok, jadi metode HPP tidak bisa diubah lagi agar nilai persediaan tetap konsisten.'}
                        </Pemberitahuan>
                    ) : null}
                    <GrupRadio
                        legenda="Metode HPP"
                        nilai={metode}
                        opsi={OpsiMetodeHpp.map((opsi) => ({
                            Nilai: opsi.Nilai,
                            Label: opsi.Label,
                            Keterangan: opsi.Keterangan,
                        }))}
                        saatBerubah={AturMetode}
                        galat={galat.MetodeHpp}
                        disabled={MetodeHppTerkunci}
                        {...(MetodeHppTerkunci
                            ? {}
                            : {
                                  keterangan: 'Pilih sebelum stok awal pertama diposting. Setelah itu metode terkunci.',
                              })}
                    />
                </PanelKatalog>

                <PanelKatalog judul="Stok minus" idJudul="judul-stok-minus">
                    <KotakCentang
                        label="Izinkan penjualan dan pengeluaran saat stok tidak cukup (stok minus)"
                        nilai={bolehMinus}
                        saatBerubah={AturBolehMinus}
                    />
                    <p className="text-keterangan text-teks-sekunder">
                        Berguna untuk usaha yang menjual sebelum stok dicatat, misalnya F&amp;B. Pengaturan per produk
                        di halaman produk menimpa pengaturan ini. Produk dengan batch atau nomor seri tidak pernah boleh
                        minus.
                    </p>
                    {galat.StokBolehMinus ? (
                        <p className="text-keterangan font-semibold text-bahaya">{galat.StokBolehMinus}</p>
                    ) : null}
                </PanelKatalog>

                <div className="flex flex-wrap items-center gap-3">
                    <Tombol type="submit" memproses={memproses && !konfirmasi} disabled={!berubah}>
                        Simpan pengaturan
                    </Tombol>
                    {!berubah ? <span className="text-keterangan text-teks-sekunder">Belum ada perubahan.</span> : null}
                </div>
            </form>

            {konfirmasi ? (
                <DialogKonfirmasi
                    judul={`Ubah metode HPP ke ${labelMetode}?`}
                    labelAksi="Ubah metode HPP"
                    varian="utama"
                    memproses={memproses}
                    saatKonfirmasi={KirimPengaturan}
                    saatBatal={() => AturKonfirmasi(false)}
                >
                    <p>Semua barang yang keluar akan dinilai dengan metode ini.</p>
                    <p>
                        Setelah mutasi stok pertama tercatat (misalnya stok awal diposting), metode tidak bisa diubah
                        lagi.
                    </p>
                    {galat.MetodeHpp ? <span className="font-semibold text-bahaya">{galat.MetodeHpp}</span> : null}
                </DialogKonfirmasi>
            ) : null}
        </TataLetakAplikasi>
    );
}
