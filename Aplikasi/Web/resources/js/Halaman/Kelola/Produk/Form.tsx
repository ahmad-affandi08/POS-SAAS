import { Link, useForm } from '@inertiajs/react';
import { useId, useRef, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { AmbilGalatBerawalan, CekAdaGalat } from '@/Komponen/Katalog/BantuanKatalog';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import DaftarTab, { type ItemTab } from '@/Komponen/Katalog/DaftarTab';
import PenyuntingAtributVarian from '@/Komponen/Katalog/PenyuntingAtributVarian';
import PenyuntingSatuanProduk, {
    GantiSatuanDasar,
    SiapkanSatuanDasar,
} from '@/Komponen/Katalog/PenyuntingSatuanProduk';
import GrupRadio from '@/Komponen/Katalog/GrupRadio';
import KepalaProduk from '@/Komponen/Katalog/KepalaProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import TabelHargaBertingkat, { PeriksaBarisHarga } from '@/Komponen/Katalog/TabelHargaBertingkat';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { Field, FieldLabel, FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { Switch } from '@/Komponen/Ui/switch';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type {
    AturanJenisProduk,
    FormProduk,
    JenisProduk,
    PelacakanProduk,
    PropsFormProduk,
    TigaKeadaan,
} from '@/Tipe/Katalog';
import { CekBatasPenuh, FormatBatas } from '@/Tipe/Organisasi';

type KunciTab = 'Umum' | 'Satuan' | 'Harga' | 'Varian' | 'Pajak';

/** Kunci galat server per tab (untuk penanda "perlu diperbaiki" dan pindah tab otomatis). */
const galatPerTab: Record<KunciTab, string[]> = {
    Umum: ['Nama', 'NamaStruk', 'Sku', 'Jenis', 'UuidKategori', 'Merek', 'UuidSatuanDasar', 'Pelacakan'],
    Satuan: ['Satuan'],
    Harga: [],
    Varian: ['AtributVarian'],
    Pajak: ['UuidKelompokPajak', 'HargaTermasukPajak', 'BolehMinus', 'TampilDiPos', 'TampilOnline'],
};

/** Galat harga awal (`Satuan.{i}.HargaAwal…`) tampil di tab Harga, bukan tab Satuan. */
function CekGalatTab(galat: Record<string, string | undefined>, tab: KunciTab): boolean {
    const hargaAwal = Object.entries(galat).some(
        ([kunci, pesan]) => Boolean(pesan) && /^Satuan\.\d+\.HargaAwal/.test(kunci),
    );

    if (tab === 'Harga') {
        return hargaAwal;
    }

    if (tab === 'Satuan') {
        return Object.entries(galat).some(
            ([kunci, pesan]) =>
                Boolean(pesan) &&
                (kunci === 'Satuan' || kunci.startsWith('Satuan.')) &&
                !/^Satuan\.\d+\.HargaAwal/.test(kunci),
        );
    }

    return CekAdaGalat(galat, galatPerTab[tab]);
}

const opsiPelacakan: { Nilai: PelacakanProduk; Label: string; Keterangan: string }[] = [
    { Nilai: 'Tidak', Label: 'Tidak dilacak', Keterangan: 'Stok dihitung per jumlah saja.' },
    { Nilai: 'Batch', Label: 'Nomor batch', Keterangan: 'Untuk barang dengan tanggal kedaluwarsa.' },
    {
        Nilai: 'Seri',
        Label: 'Nomor seri',
        Keterangan:
            'Satu nomor per unit, misal ponsel. Satuan dasar harus bilangan bulat; jual saat stok kosong dimatikan.',
    },
];

/** Keterangan singkat aturan jenis produk untuk pengguna. */
export function JelaskanJenis(aturan: AturanJenisProduk | undefined): string {
    if (!aturan) {
        return '';
    }

    if (aturan.Nilai === 'IndukVarian') {
        return 'Produk bervarian: dijual lewat varian (misal ukuran atau warna). Tidak dihitung ke batas produk paket.';
    }

    const bagian = [
        aturan.PunyaStok ? 'punya stok' : 'tanpa stok sendiri',
        aturan.BisaDijual ? 'dijual di kasir' : 'tidak dijual di kasir',
    ];

    if (aturan.BolehResep) {
        bagian.push('memakai resep');
    }

    if (aturan.BolehKomponen) {
        bagian.push('berisi produk lain');
    }

    return `${aturan.Label}: ${bagian.join(', ')}.`;
}

/** F-03 buat/ubah produk (DesainF03 E.3). Body JSON = `FormProduk`; galat server dipetakan ke isian dan tab. */
export default function HalamanFormProduk({
    Mode,
    Produk,
    Kepala,
    Kategori,
    Satuan,
    KelompokPajak,
    Jenis,
    JenisTerkunci,
    BatasSku,
    Pengaturan,
    Izin,
}: PropsFormProduk) {
    const formulir = useForm<FormProduk>({
        ...Produk,
        Satuan: SiapkanSatuanDasar(Produk.Satuan, Produk.UuidSatuanDasar),
    });
    const data = formulir.data;
    const galat = formulir.errors as Record<string, string | undefined>;
    const elemenForm = useRef<HTMLFormElement>(null);
    const idForm = useId();
    const [tabAktif, AturTabAktif] = useState<KunciTab>('Umum');
    const [periksaHarga, AturPeriksaHarga] = useState(false);
    const aturan = Jenis.find((item) => item.Nilai === data.Jenis);
    const induk = data.Jenis === 'IndukVarian';
    const bisaDijual = aturan?.BisaDijual ?? false;
    const satuanDasar = Satuan.find((item) => item.Uuid === data.UuidSatuanDasar);
    const batasPenuh = Mode === 'Buat' && (aturan?.DihitungBatasSku ?? false) && CekBatasPenuh(BatasSku);
    const bolehUbah = Izin.Kelola;

    const Atur = <K extends keyof FormProduk>(kunci: K, nilai: FormProduk[K]) =>
        formulir.setData((lama) => ({ ...lama, [kunci]: nilai }));

    const GantiJenis = (nilai: JenisProduk) => {
        const aturanBaru = Jenis.find((item) => item.Nilai === nilai);

        formulir.setData((lama) => ({
            ...lama,
            Jenis: nilai,
            Pelacakan: aturanBaru?.BolehPelacakan ? lama.Pelacakan : 'Tidak',
            TampilDiPos: aturanBaru?.BisaDijual || nilai === 'IndukVarian' ? lama.TampilDiPos : false,
            AtributVarian: nilai === 'IndukVarian' ? lama.AtributVarian : [],
        }));
    };

    const satuanBaru = data.Satuan.map((baris, indeks) => ({ baris, indeks })).filter(
        ({ baris }) => baris.Uuid === null,
    );

    const PeriksaLokal = (): KunciTab | null => {
        if (bisaDijual && !induk) {
            const salah = satuanBaru.some(({ baris }) => {
                const opsi = Satuan.find((item) => item.Uuid === baris.UuidSatuan);
                const hasil = PeriksaBarisHarga(baris.HargaAwal, {
                    wajibDasar: true,
                    bolehDesimal: opsi?.BolehDesimal ?? false,
                });

                return Object.keys(hasil.perBaris).length > 0 || hasil.umum !== null;
            });

            if (salah) {
                return 'Harga';
            }
        }

        return null;
    };

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const tabSalah = PeriksaLokal();

        if (tabSalah !== null) {
            AturPeriksaHarga(true);
            AturTabAktif(tabSalah);
            FokusGalatPertama(elemenForm.current);

            return;
        }

        const opsi = {
            preserveScroll: true,
            onError: (galatBaru: Record<string, string | undefined>) => {
                const tab = (['Umum', 'Satuan', 'Harga', 'Varian', 'Pajak'] as KunciTab[]).find((kunci) =>
                    CekGalatTab(galatBaru, kunci),
                );

                if (tab) {
                    AturTabAktif(tab);
                }

                FokusGalatPertama(elemenForm.current);
            },
        };

        if (Mode === 'Buat') {
            formulir.post('/kelola/produk', opsi);
        } else {
            formulir.put(`/kelola/produk/${data.Uuid}`, opsi);
        }
    };

    const tabDasar: ItemTab<KunciTab>[] = [
        { Kunci: 'Umum', Label: 'Umum' },
        { Kunci: 'Satuan', Label: 'Satuan & barcode' },
        { Kunci: 'Harga', Label: 'Harga' },
        ...(induk ? [{ Kunci: 'Varian' as const, Label: 'Varian' }] : []),
        { Kunci: 'Pajak', Label: 'Pajak & tampilan' },
    ];
    const daftarTab = tabDasar.map((item) => ({ ...item, AdaGalat: CekGalatTab(galat, item.Kunci) }));

    const panelUmum = (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <BidangTeks
                    label="Nama produk"
                    nilai={data.Nama}
                    saatBerubah={(nilai) => Atur('Nama', nilai)}
                    galat={galat.Nama}
                    maxLength={150}
                    required
                    autoFocus={Mode === 'Buat'}
                />
            </div>
            <BidangTeks
                label="Nama di struk (opsional)"
                nilai={data.NamaStruk}
                saatBerubah={(nilai) => Atur('NamaStruk', nilai)}
                galat={galat.NamaStruk}
                keterangan="Nama pendek untuk struk dan dapur. Kosongkan untuk memakai nama produk."
                maxLength={40}
            />
            <BidangTeks
                label="SKU (opsional)"
                nilai={data.Sku}
                saatBerubah={(nilai) => Atur('Sku', nilai)}
                galat={galat.Sku}
                keterangan="Kode unik produk. Kosongkan agar dibuat otomatis, misal PRD-000123."
                maxLength={64}
                kode
            />
            <div className="flex flex-col gap-1">
                {JenisTerkunci ? (
                    <BidangTeks
                        label="Jenis produk"
                        nilai={aturan?.Label ?? data.Jenis}
                        saatBerubah={() => undefined}
                        galat={galat.Jenis}
                        disabled
                    />
                ) : (
                    <BidangPilihan
                        label="Jenis produk"
                        nilai={data.Jenis}
                        opsi={Jenis.map((item) => ({ Nilai: item.Nilai, Label: item.Label }))}
                        saatBerubah={(nilai) => GantiJenis(nilai as JenisProduk)}
                        galat={galat.Jenis}
                    />
                )}
                <p className="text-keterangan text-teks-sekunder">
                    {JenisTerkunci
                        ? 'Jenis tidak bisa diubah karena produk sudah dipakai atau punya varian.'
                        : JelaskanJenis(aturan)}
                </p>
            </div>
            <BidangPilihan
                label="Kategori"
                nilai={data.UuidKategori ?? ''}
                kosong="Tanpa kategori"
                opsi={Kategori.map((item) => ({ Nilai: item.Uuid, Label: item.Jalur }))}
                saatBerubah={(nilai) => Atur('UuidKategori', nilai === '' ? null : nilai)}
                galat={galat.UuidKategori}
            />
            <BidangTeks
                label="Merek (opsional)"
                nilai={data.Merek}
                saatBerubah={(nilai) => Atur('Merek', nilai)}
                galat={galat.Merek}
                maxLength={100}
            />
            <div className="flex flex-col gap-1">
                <BidangPilihan
                    label="Satuan dasar"
                    nilai={data.UuidSatuanDasar}
                    kosong="Pilih satuan"
                    opsi={Satuan.map((item) => ({ Nilai: item.Uuid, Label: `${item.Nama} (${item.Simbol})` }))}
                    saatBerubah={(nilai) =>
                        formulir.setData((lama) => ({
                            ...lama,
                            UuidSatuanDasar: nilai,
                            Satuan: GantiSatuanDasar(lama.Satuan, lama.UuidSatuanDasar, nilai),
                        }))
                    }
                    galat={galat.UuidSatuanDasar}
                />
                <p className="text-keterangan text-teks-sekunder">
                    Satuan terkecil untuk stok dan resep, misal pcs, gram, atau ml.
                </p>
            </div>
            {aturan?.BolehPelacakan ? (
                <div className="sm:col-span-2">
                    <GrupRadio
                        legenda="Pelacakan stok"
                        nilai={data.Pelacakan}
                        opsi={opsiPelacakan}
                        saatBerubah={(nilai) => Atur('Pelacakan', nilai)}
                        galat={galat.Pelacakan}
                    />
                </div>
            ) : null}
        </div>
    );

    const panelHarga = (
        <div className="flex flex-col gap-4">
            {induk ? (
                <p className="text-isi text-teks-sekunder">
                    Produk bervarian tidak punya harga sendiri. Harga diisi per varian setelah varian dibuat.
                </p>
            ) : !bisaDijual ? (
                <p className="text-isi text-teks-sekunder">
                    {aturan?.Label ?? 'Jenis ini'} tidak dijual di kasir, jadi tidak perlu harga jual.
                </p>
            ) : (
                <>
                    {!Izin.UbahHarga ? <PesanHanyaLihat izin="produk.harga.ubah" objek="harga produk" /> : null}
                    {satuanBaru.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">
                            Semua satuan sudah tersimpan. Ubah harga dan harga bertingkat di{' '}
                            <Link
                                href={`/kelola/produk/${data.Uuid}/harga`}
                                className="font-semibold text-brand underline"
                            >
                                halaman Harga produk
                            </Link>
                            .
                        </p>
                    ) : (
                        satuanBaru.map(({ baris, indeks }) => {
                            const opsi = Satuan.find((item) => item.Uuid === baris.UuidSatuan);
                            const simbol = opsi?.Simbol ?? 'satuan';

                            return (
                                <section key={indeks} className="flex flex-col gap-2">
                                    <h3 className="text-label font-semibold text-teks-utama">
                                        Harga per {opsi ? `${opsi.Nama} (${simbol})` : 'satuan belum dipilih'}
                                    </h3>
                                    <TabelHargaBertingkat
                                        judul={`Harga per ${simbol}`}
                                        baris={baris.HargaAwal}
                                        saatBerubah={(nilai) =>
                                            Atur(
                                                'Satuan',
                                                data.Satuan.map((item, i) =>
                                                    i === indeks ? { ...item, HargaAwal: nilai } : item,
                                                ),
                                            )
                                        }
                                        simbolSatuan={simbol}
                                        bolehDesimal={opsi?.BolehDesimal ?? false}
                                        wajibDasar
                                        galatServer={AmbilGalatBerawalan(galat, `Satuan.${String(indeks)}.HargaAwal`)}
                                        tampilkanGalat={periksaHarga}
                                        disabled={!Izin.UbahHarga}
                                    />
                                    {galat[`Satuan.${String(indeks)}.HargaAwal`] ? (
                                        <p className="text-keterangan font-semibold text-bahaya">
                                            {galat[`Satuan.${String(indeks)}.HargaAwal`]}
                                        </p>
                                    ) : null}
                                </section>
                            );
                        })
                    )}
                    <p className="text-keterangan text-teks-sekunder">
                        Harga bertingkat berlaku otomatis di kasir, misal 1–11 pcs Rp 5.000 dan mulai 12 pcs Rp 4.500.
                        Harga per outlet, kanal, atau waktu diatur di Daftar harga.
                    </p>
                </>
            )}
        </div>
    );

    const panelPajak = (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-1 sm:col-span-2">
                <BidangPilihan
                    label={bisaDijual || induk ? 'Kelompok pajak' : 'Kelompok pajak (opsional)'}
                    nilai={data.UuidKelompokPajak ?? ''}
                    kosong="Pilih kelompok pajak"
                    opsi={KelompokPajak.map((item) => ({
                        Nilai: item.Uuid,
                        Label: `${item.Nama} · ${item.LabelKategori}`,
                    }))}
                    saatBerubah={(nilai) => Atur('UuidKelompokPajak', nilai === '' ? null : nilai)}
                    galat={galat.UuidKelompokPajak}
                />
                <p className="text-keterangan text-teks-sekunder">
                    Tarif diambil dari tabel tarif yang berlaku saat transaksi. Kelompok baru dibuat di menu Kelompok
                    pajak.
                </p>
            </div>
            <GrupRadio<TigaKeadaan>
                legenda="Harga sudah termasuk pajak?"
                nilai={data.HargaTermasukPajak}
                opsi={[
                    { Nilai: 'Ikut', Label: `Ikuti pengaturan outlet (${Pengaturan.HargaTermasukPajakOutlet})` },
                    { Nilai: 'Ya', Label: 'Ya, harga jual sudah termasuk pajak' },
                    { Nilai: 'Tidak', Label: 'Tidak, pajak ditambahkan di atas harga' },
                ]}
                saatBerubah={(nilai) => Atur('HargaTermasukPajak', nilai)}
                galat={galat.HargaTermasukPajak}
            />
            {aturan?.PunyaStok ? (
                <GrupRadio<TigaKeadaan>
                    legenda="Boleh dijual saat stok kosong?"
                    nilai={data.BolehMinus}
                    opsi={[
                        {
                            Nilai: 'Ikut',
                            Label: `Ikuti pengaturan usaha (${Pengaturan.StokBolehMinus ? 'boleh' : 'tidak boleh'})`,
                        },
                        { Nilai: 'Ya', Label: 'Boleh, stok bisa minus' },
                        { Nilai: 'Tidak', Label: 'Tidak boleh' },
                    ]}
                    saatBerubah={(nilai) => Atur('BolehMinus', nilai)}
                    galat={galat.BolehMinus}
                    disabled={data.Pelacakan === 'Seri'}
                />
            ) : null}
            <FieldSet className="gap-1 sm:col-span-2">
                <FieldLegend variant="label" className="mb-1 text-label font-semibold text-teks-utama">
                    Tampilkan produk
                </FieldLegend>
                {bisaDijual || induk ? (
                    <Field orientation="horizontal" className="min-h-10 items-center">
                        <Switch
                            id={`${idForm}-tampil-pos`}
                            checked={data.TampilDiPos}
                            onCheckedChange={(nilai) => Atur('TampilDiPos', nilai)}
                        />
                        <FieldLabel htmlFor={`${idForm}-tampil-pos`} className="text-isi font-normal text-teks-utama">
                            {induk ? 'Tampilkan sebagai grup varian di kasir' : 'Tampil di kasir (POS)'}
                        </FieldLabel>
                    </Field>
                ) : (
                    <p className="text-keterangan text-teks-sekunder">Jenis ini tidak tampil di kasir.</p>
                )}
                <Field orientation="horizontal" className="min-h-10 items-center">
                    <Switch
                        id={`${idForm}-tampil-online`}
                        checked={data.TampilOnline}
                        onCheckedChange={(nilai) => Atur('TampilOnline', nilai)}
                    />
                    <FieldLabel htmlFor={`${idForm}-tampil-online`} className="text-isi font-normal text-teks-utama">
                        Tampil di toko online
                    </FieldLabel>
                </Field>
            </FieldSet>
        </div>
    );

    return (
        <TataLetakAplikasi judul={Mode === 'Buat' ? 'Tambah produk' : 'Ubah produk'}>
            {Kepala ? <KepalaProduk kepala={Kepala} tabAktif="Ringkasan" /> : null}
            {!bolehUbah ? <PesanHanyaLihat izin="produk.kelola" objek="produk ini" /> : null}
            {batasPenuh ? (
                <Pemberitahuan jenis="peringatan" judul="Batas produk paket sudah tercapai">
                    {FormatBatas(BatasSku, 'produk')}. Arsipkan produk lain atau tingkatkan paket di menu Langganan
                    sebelum menambah produk jenis ini.
                </Pemberitahuan>
            ) : null}
            <form ref={elemenForm} onSubmit={Kirim} noValidate className="flex flex-col gap-4">
                <RingkasanGalatFormulir galat={galat} />
                <DaftarGalatServer
                    galat={galat}
                    kecuali={Object.keys(galat).filter((kunci) =>
                        (Object.keys(galatPerTab) as KunciTab[]).some((tab) => CekGalatTab({ [kunci]: 'x' }, tab)),
                    )}
                />
                <Card className="gap-0 rounded-panel p-4 shadow-none">
                    <DaftarTab
                        label="Bagian formulir produk"
                        tab={daftarTab}
                        aktif={tabAktif}
                        saatPilih={AturTabAktif}
                        panel={{
                            Umum: panelUmum,
                            Satuan: (
                                <PenyuntingSatuanProduk
                                    satuan={data.Satuan}
                                    uuidSatuanDasar={data.UuidSatuanDasar}
                                    opsiSatuan={Satuan}
                                    saatBerubah={(nilai) => Atur('Satuan', nilai)}
                                    galat={galat}
                                    disabled={!bolehUbah}
                                />
                            ),
                            Harga: panelHarga,
                            Varian: (
                                <div className="flex flex-col gap-3">
                                    <p className="text-isi text-teks-sekunder">
                                        Tentukan atribut dan nilainya di sini. Setelah produk disimpan, buat varian dari
                                        halaman produk: satu varian untuk setiap kombinasi.
                                    </p>
                                    <PenyuntingAtributVarian
                                        nilai={data.AtributVarian}
                                        saatBerubah={(nilai) => Atur('AtributVarian', nilai)}
                                        galat={AmbilGalatBerawalan(galat, 'AtributVarian')}
                                        disabled={!bolehUbah}
                                    />
                                    {galat.AtributVarian ? (
                                        <p className="text-keterangan font-semibold text-bahaya">
                                            {galat.AtributVarian}
                                        </p>
                                    ) : null}
                                </div>
                            ),
                            Pajak: panelPajak,
                        }}
                    />
                </Card>
                {satuanDasar === undefined && data.UuidSatuanDasar !== '' ? (
                    <p className="text-keterangan text-bahaya">
                        Satuan dasar tidak ditemukan. Pilih ulang satuan dasar.
                    </p>
                ) : null}
                <div className="flex flex-wrap gap-2">
                    {bolehUbah ? (
                        <Tombol type="submit" memproses={formulir.processing} disabled={batasPenuh}>
                            {Mode === 'Buat' ? 'Simpan produk' : 'Simpan perubahan'}
                        </Tombol>
                    ) : null}
                    <Button asChild variant="outline" className="h-8 pointer-coarse:h-11">
                        <Link href={Mode === 'Buat' ? '/kelola/produk' : `/kelola/produk/${data.Uuid}`}>Batal</Link>
                    </Button>
                </div>
            </form>
        </TataLetakAplikasi>
    );
}
