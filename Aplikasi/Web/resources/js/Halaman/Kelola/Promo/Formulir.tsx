import { Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import PemilihTanggal from '@/Komponen/Tanggal/PemilihTanggal';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type {
    JenisAksiPromo,
    JenisKondisiPromo,
    JenisUlangTahunPromo,
    PeriodeBatasPelangganPromo,
    PropsFormulirPromo,
} from '@/Tipe/Promo';

const alamat = '/kelola/promo';

export const opsiAksi: { Nilai: JenisAksiPromo; Label: string }[] = [
    { Nilai: 'DiskonPersenItem', Label: 'Diskon persen barang' },
    { Nilai: 'DiskonTetapItem', Label: 'Potongan rupiah per barang' },
    { Nilai: 'HargaSpesial', Label: 'Harga spesial per barang' },
    { Nilai: 'BeliXGratisY', Label: 'Beli X gratis Y' },
    { Nilai: 'BundelHargaTetap', Label: 'Bundel harga tetap (misal 2 kopi Rp 30.000)' },
    { Nilai: 'DiskonPersenPesanan', Label: 'Diskon persen pesanan' },
    { Nilai: 'DiskonTetapPesanan', Label: 'Potongan rupiah pesanan' },
];

const opsiKondisi: { Nilai: JenisKondisiPromo; Label: string }[] = [
    { Nilai: 'Semua', Label: 'Semua barang' },
    { Nilai: 'Produk', Label: 'Produk tertentu' },
    { Nilai: 'Kategori', Label: 'Kategori tertentu' },
];

const opsiHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map((label, indeks) => ({
    nilai: String(indeks + 1),
    label,
}));

type Isian = {
    Kode: string;
    Nama: string;
    Prioritas: string;
    Eksklusif: boolean;
    WajibVoucher: boolean;
    TanggalMulai: string;
    TanggalSelesai: string;
    Kuota: string;
    Hari: string[];
    JamMulai: string;
    JamSelesai: string;
    Outlet: string[];
    Kanal: string[];
    Tier: string[];
    MinimalSubtotal: string;
    JenisKondisi: JenisKondisiPromo;
    UuidKondisi: string[];
    JumlahMinimal: string;
    JenisAksi: JenisAksiPromo;
    Persen: string;
    Jumlah: string;
    Harga: string;
    Beli: string;
    Gratis: string;
    PersenGratis: string;
    BatasPerTransaksi: string;
    MetodeBayar: string[];
    UlangTahun: JenisUlangTahunPromo | '';
    HariUlangTahun: string;
    TransaksiPertama: boolean;
    BatasPerPelanggan: string;
    PeriodeBatasPelanggan: PeriodeBatasPelangganPromo;
};

const HapusNolPecahan = (nilai: string | undefined): string => (nilai ?? '').replace(/\.0+$/, '');

/**
 * F-16c: formulir promo. Barang pemicu, aksi, syarat (minimal belanja, tier, kanal, outlet), waktu (tanggal, hari, jam
 * lokal outlet), dan batas (kuota total, batas per transaksi untuk beli X gratis Y & bundel). Bagian 3: metode bayar,
 * ulang tahun, transaksi pertama, dan batas per pelanggan.
 */
export default function HalamanFormulirPromo({
    Promo,
    OpsiOutlet,
    OpsiTier,
    OpsiKategori,
    OpsiKanal,
    OpsiMetodeBayar,
    OpsiUlangTahun,
    OpsiPeriodeBatas,
    FiturAktif,
}: PropsFormulirPromo) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const d = Promo?.Definisi;
    const [namaProduk, AturNamaProduk] = useState<Record<string, string>>(Promo?.NamaProduk ?? {});
    const [memproses, AturMemproses] = useState(false);
    const [isian, AturIsian] = useState<Isian>({
        Kode: Promo?.Kode ?? '',
        Nama: Promo?.Nama ?? '',
        Prioritas: String(Promo?.Prioritas ?? 0),
        Eksklusif: Promo?.Eksklusif ?? false,
        WajibVoucher: d?.WajibVoucher ?? false,
        TanggalMulai: Promo?.TanggalMulai ?? '',
        TanggalSelesai: Promo?.TanggalSelesai ?? '',
        Kuota: Promo?.Kuota === null || Promo?.Kuota === undefined ? '' : String(Promo.Kuota),
        Hari: (d?.Hari ?? []).map(String),
        JamMulai: d?.JamMulai ?? '',
        JamSelesai: d?.JamSelesai ?? '',
        Outlet: d?.Outlet ?? [],
        Kanal: d?.Kanal ?? [],
        Tier: d?.Tier ?? [],
        MinimalSubtotal: HapusNolPecahan(d?.MinimalSubtotal),
        JenisKondisi: d?.Kondisi.Jenis ?? 'Semua',
        UuidKondisi: d?.Kondisi.Uuid ?? [],
        JumlahMinimal: HapusNolPecahan(d?.Kondisi.JumlahMinimal),
        JenisAksi: d?.Aksi.Jenis ?? 'DiskonPersenItem',
        Persen: HapusNolPecahan(d?.Aksi.Persen),
        Jumlah: HapusNolPecahan(d?.Aksi.Jumlah),
        Harga: HapusNolPecahan(d?.Aksi.Harga),
        Beli: d?.Aksi.Beli === undefined ? '2' : String(d.Aksi.Beli),
        Gratis: d?.Aksi.Gratis === undefined ? '1' : String(d.Aksi.Gratis),
        PersenGratis: HapusNolPecahan(d?.Aksi.PersenGratis) || '100',
        BatasPerTransaksi:
            d?.BatasPerTransaksi === null || d?.BatasPerTransaksi === undefined ? '' : String(d.BatasPerTransaksi),
        MetodeBayar: d?.MetodeBayar ?? [],
        UlangTahun: d?.UlangTahun?.Jenis ?? '',
        HariUlangTahun: String(d?.UlangTahun?.Hari ?? 7),
        TransaksiPertama: d?.TransaksiPertama ?? false,
        BatasPerPelanggan: d?.BatasPerPelanggan === undefined ? '' : String(d.BatasPerPelanggan.Jumlah),
        PeriodeBatasPelanggan: d?.BatasPerPelanggan?.Periode ?? 'Hari',
    });
    const Ubah = (ubah: Partial<Isian>) => AturIsian({ ...isian, ...ubah });
    const aksi = isian.JenisAksi;
    const pakaiPersen = aksi === 'DiskonPersenItem' || aksi === 'DiskonPersenPesanan';
    const pakaiJumlah = aksi === 'DiskonTetapItem' || aksi === 'DiskonTetapPesanan';
    const pakaiHarga = aksi === 'HargaSpesial' || aksi === 'BundelHargaTetap';
    const bertingkat = aksi === 'BeliXGratisY' || aksi === 'BundelHargaTetap';

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const KosongJadiNull = (nilai: string) => (nilai === '' ? null : nilai);
        const data = {
            Kode: isian.Kode,
            Nama: isian.Nama,
            Prioritas: Number(isian.Prioritas || '0'),
            Eksklusif: isian.Eksklusif,
            WajibVoucher: isian.WajibVoucher,
            TanggalMulai: KosongJadiNull(isian.TanggalMulai),
            TanggalSelesai: KosongJadiNull(isian.TanggalSelesai),
            Kuota: isian.Kuota === '' ? null : Number(isian.Kuota),
            Hari: isian.Hari.map(Number),
            JamMulai: KosongJadiNull(isian.JamMulai),
            JamSelesai: KosongJadiNull(isian.JamSelesai),
            Outlet: isian.Outlet,
            Kanal: isian.Kanal,
            Tier: isian.Tier,
            MinimalSubtotal: isian.MinimalSubtotal === '' ? '0' : isian.MinimalSubtotal,
            JenisKondisi: isian.JenisKondisi,
            UuidKondisi: isian.JenisKondisi === 'Semua' ? [] : isian.UuidKondisi,
            JumlahMinimal: aksi === 'BundelHargaTetap' || isian.JumlahMinimal !== '' ? isian.JumlahMinimal || '0' : '0',
            JenisAksi: aksi,
            Persen: pakaiPersen ? KosongJadiNull(isian.Persen) : null,
            Jumlah: pakaiJumlah ? KosongJadiNull(isian.Jumlah) : null,
            Harga: pakaiHarga ? KosongJadiNull(isian.Harga) : null,
            Beli: aksi === 'BeliXGratisY' ? Number(isian.Beli || '0') : null,
            Gratis: aksi === 'BeliXGratisY' ? Number(isian.Gratis || '0') : null,
            PersenGratis: aksi === 'BeliXGratisY' ? KosongJadiNull(isian.PersenGratis) : null,
            BatasPerTransaksi: bertingkat && isian.BatasPerTransaksi !== '' ? Number(isian.BatasPerTransaksi) : null,
            MetodeBayar: isian.MetodeBayar,
            UlangTahun: KosongJadiNull(isian.UlangTahun),
            HariUlangTahun: isian.UlangTahun === 'Rentang' ? Number(isian.HariUlangTahun || '0') : null,
            TransaksiPertama: isian.TransaksiPertama,
            BatasPerPelanggan: isian.BatasPerPelanggan === '' ? null : Number(isian.BatasPerPelanggan),
            PeriodeBatasPelanggan: isian.PeriodeBatasPelanggan,
        };
        const opsi = { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) };

        if (Promo === null) {
            router.post(alamat, data, opsi);
        } else {
            router.put(`${alamat}/${Promo.Uuid}`, data, opsi);
        }
    };

    return (
        <TataLetakAplikasi judul={Promo === null ? 'Tambah promo' : `Ubah promo ${Promo.Nama}`}>
            {FiturAktif ? null : (
                <Pemberitahuan jenis="peringatan" judul="Mesin promo tersedia di paket Pro ke atas">
                    Promo tersimpan, tetapi baru diterapkan di kasir setelah paket dinaikkan.
                </Pemberitahuan>
            )}
            <form onSubmit={Simpan} className="flex max-w-3xl flex-col gap-4" aria-label="Formulir promo" noValidate>
                <Card className="gap-4 rounded-panel p-4 shadow-none">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Promo</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <BidangTeks
                            label="Kode promo"
                            nilai={isian.Kode}
                            saatBerubah={(nilai) => {
                                if (Promo === null) {
                                    Ubah({ Kode: nilai.toUpperCase() });
                                }
                            }}
                            galat={galat.Kode}
                            keterangan={
                                Promo === null ? 'Huruf, angka, - atau _. Tidak bisa diubah.' : 'Tidak bisa diubah.'
                            }
                            kode
                            required={Promo === null}
                        />
                        <BidangTeks
                            label="Nama promo"
                            nilai={isian.Nama}
                            saatBerubah={(nilai) => Ubah({ Nama: nilai })}
                            galat={galat.Nama}
                            required
                        />
                        <BidangJumlah
                            label="Prioritas"
                            nilai={isian.Prioritas}
                            saatBerubah={(nilai) => Ubah({ Prioritas: nilai })}
                            desimal={0}
                            digitBulat={3}
                            keterangan="Angka besar dievaluasi lebih dulu."
                            galat={galat.Prioritas}
                        />
                    </div>
                    <KotakCentang
                        label="Eksklusif (tidak digabung dengan promo lain)"
                        nilai={isian.Eksklusif}
                        saatBerubah={(nilai) => Ubah({ Eksklusif: nilai })}
                    />
                    <KotakCentang
                        label="Wajib kode voucher (kasir memasukkan kode, perlu online)"
                        nilai={isian.WajibVoucher}
                        saatBerubah={(nilai) => Ubah({ WajibVoucher: nilai })}
                    />
                </Card>

                <Card className="gap-4 rounded-panel p-4 shadow-none">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Potongan</h2>
                    <BidangPilihan
                        label="Jenis potongan"
                        nilai={aksi}
                        opsi={opsiAksi}
                        saatBerubah={(nilai) => Ubah({ JenisAksi: nilai as JenisAksiPromo })}
                        required
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        {pakaiPersen ? (
                            <BidangJumlah
                                label="Persen diskon"
                                nilai={isian.Persen}
                                saatBerubah={(nilai) => Ubah({ Persen: nilai })}
                                desimal={2}
                                digitBulat={3}
                                akhiran="%"
                                galat={galat.Persen}
                                required
                            />
                        ) : null}
                        {pakaiJumlah ? (
                            <BidangUang
                                label={aksi === 'DiskonTetapItem' ? 'Potongan per barang' : 'Potongan pesanan'}
                                nilai={isian.Jumlah}
                                saatBerubah={(nilai) => Ubah({ Jumlah: nilai })}
                                galat={galat.Jumlah}
                                required
                            />
                        ) : null}
                        {pakaiHarga ? (
                            <BidangUang
                                label={aksi === 'HargaSpesial' ? 'Harga spesial per barang' : 'Harga satu bundel'}
                                nilai={isian.Harga}
                                saatBerubah={(nilai) => Ubah({ Harga: nilai })}
                                galat={galat.Harga}
                                required
                            />
                        ) : null}
                        {aksi === 'BundelHargaTetap' ? (
                            <BidangJumlah
                                label="Isi satu bundel"
                                nilai={isian.JumlahMinimal}
                                saatBerubah={(nilai) => Ubah({ JumlahMinimal: nilai })}
                                desimal={0}
                                digitBulat={2}
                                akhiran="barang"
                                galat={galat.JumlahMinimal}
                                required
                            />
                        ) : null}
                        {aksi === 'BeliXGratisY' ? (
                            <>
                                <BidangJumlah
                                    label="Beli"
                                    nilai={isian.Beli}
                                    saatBerubah={(nilai) => Ubah({ Beli: nilai })}
                                    desimal={0}
                                    digitBulat={2}
                                    akhiran="barang"
                                    galat={galat.Beli}
                                    required
                                />
                                <BidangJumlah
                                    label="Gratis"
                                    nilai={isian.Gratis}
                                    saatBerubah={(nilai) => Ubah({ Gratis: nilai })}
                                    desimal={0}
                                    digitBulat={2}
                                    akhiran="barang"
                                    required
                                />
                                <BidangJumlah
                                    label="Potongan barang gratis"
                                    nilai={isian.PersenGratis}
                                    saatBerubah={(nilai) => Ubah({ PersenGratis: nilai })}
                                    desimal={2}
                                    digitBulat={3}
                                    akhiran="%"
                                    keterangan="100% = gratis; 50% = setengah harga. Yang dipotong barang termurah dalam satu set."
                                    galat={galat.PersenGratis}
                                />
                            </>
                        ) : null}
                        {bertingkat ? (
                            <BidangJumlah
                                label="Batas per transaksi"
                                nilai={isian.BatasPerTransaksi}
                                saatBerubah={(nilai) => Ubah({ BatasPerTransaksi: nilai })}
                                desimal={0}
                                digitBulat={3}
                                akhiran="set"
                                keterangan="Kosongkan untuk tanpa batas."
                                galat={galat.BatasPerTransaksi}
                            />
                        ) : null}
                    </div>
                </Card>

                <Card className="gap-4 rounded-panel p-4 shadow-none">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Barang</h2>
                    <BidangPilihan
                        label="Berlaku untuk"
                        nilai={isian.JenisKondisi}
                        opsi={opsiKondisi}
                        saatBerubah={(nilai) => Ubah({ JenisKondisi: nilai as JenisKondisiPromo, UuidKondisi: [] })}
                        required
                    />
                    {isian.JenisKondisi === 'Produk' ? (
                        <>
                            <PemilihProduk
                                label="Tambah produk"
                                jenis={[]}
                                kecuali={isian.UuidKondisi}
                                saatPilih={(produk) => {
                                    AturNamaProduk({ ...namaProduk, [produk.Uuid]: produk.Nama });
                                    Ubah({ UuidKondisi: [...isian.UuidKondisi, produk.Uuid] });
                                }}
                                galat={galat.UuidKondisi}
                            />
                            <ul className="flex flex-wrap gap-2" aria-label="Produk promo">
                                {isian.UuidKondisi.map((uuid) => (
                                    <li key={uuid}>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                Ubah({ UuidKondisi: isian.UuidKondisi.filter((u) => u !== uuid) })
                                            }
                                            aria-label={`Hapus ${namaProduk[uuid] ?? uuid} dari promo`}
                                        >
                                            {namaProduk[uuid] ?? uuid} ×
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : null}
                    {isian.JenisKondisi === 'Kategori' ? (
                        <GrupCentang
                            legenda="Kategori"
                            opsi={OpsiKategori.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                            terpilih={isian.UuidKondisi}
                            saatBerubah={(terpilih) => Ubah({ UuidKondisi: terpilih })}
                            galat={galat.UuidKondisi}
                            required
                        />
                    ) : null}
                    {aksi !== 'BundelHargaTetap' && aksi !== 'BeliXGratisY' ? (
                        <BidangJumlah
                            label="Jumlah barang minimal"
                            nilai={isian.JumlahMinimal}
                            saatBerubah={(nilai) => Ubah({ JumlahMinimal: nilai })}
                            desimal={4}
                            digitBulat={6}
                            keterangan="Kosongkan bila tanpa minimal."
                            galat={galat.JumlahMinimal}
                        />
                    ) : null}
                </Card>

                <Card className="gap-4 rounded-panel p-4 shadow-none">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Syarat & waktu</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <BidangUang
                            label="Minimal belanja"
                            nilai={isian.MinimalSubtotal}
                            saatBerubah={(nilai) => Ubah({ MinimalSubtotal: nilai })}
                            galat={galat.MinimalSubtotal}
                        />
                        <BidangJumlah
                            label="Kuota total"
                            nilai={isian.Kuota}
                            saatBerubah={(nilai) => Ubah({ Kuota: nilai })}
                            desimal={0}
                            digitBulat={8}
                            akhiran="transaksi"
                            keterangan="Kosongkan untuk tanpa batas."
                            galat={galat.Kuota}
                        />
                        <PemilihTanggal
                            label="Mulai tanggal"
                            nilai={isian.TanggalMulai}
                            saatBerubah={(nilai) => Ubah({ TanggalMulai: nilai })}
                            galat={galat.TanggalMulai}
                        />
                        <PemilihTanggal
                            label="Sampai tanggal"
                            nilai={isian.TanggalSelesai}
                            saatBerubah={(nilai) => Ubah({ TanggalSelesai: nilai })}
                            galat={galat.TanggalSelesai ?? galat.SelesaiPada}
                        />
                        <BidangTeks
                            label="Jam mulai"
                            nilai={isian.JamMulai}
                            saatBerubah={(nilai) => Ubah({ JamMulai: nilai })}
                            keterangan="Format JJ:MM, jam lokal outlet. Kosongkan untuk sepanjang hari."
                            galat={galat.JamMulai}
                        />
                        <BidangTeks
                            label="Jam selesai"
                            nilai={isian.JamSelesai}
                            saatBerubah={(nilai) => Ubah({ JamSelesai: nilai })}
                            keterangan="Tidak termasuk jam ini, misal 17:00 = sampai 16:59."
                            galat={galat.JamSelesai}
                        />
                    </div>
                    <GrupCentang
                        legenda="Hari (kosong = setiap hari)"
                        opsi={opsiHari}
                        terpilih={isian.Hari}
                        saatBerubah={(terpilih) => Ubah({ Hari: terpilih })}
                        galat={galat.Hari}
                    />
                    <GrupCentang
                        legenda="Kanal (kosong = semua)"
                        opsi={OpsiKanal.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                        terpilih={isian.Kanal}
                        saatBerubah={(terpilih) => Ubah({ Kanal: terpilih })}
                    />
                    {OpsiTier.length > 0 ? (
                        <GrupCentang
                            legenda="Khusus tier pelanggan (kosong = semua pembeli)"
                            opsi={OpsiTier.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                            terpilih={isian.Tier}
                            saatBerubah={(terpilih) => Ubah({ Tier: terpilih })}
                        />
                    ) : null}
                    {OpsiOutlet.length > 1 ? (
                        <GrupCentang
                            legenda="Outlet (kosong = semua)"
                            opsi={OpsiOutlet.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                            terpilih={isian.Outlet}
                            saatBerubah={(terpilih) => Ubah({ Outlet: terpilih })}
                        />
                    ) : null}
                </Card>

                <Card className="gap-4 rounded-panel p-4 shadow-none">
                    <h2 className="text-subjudul font-semibold text-teks-utama">Syarat pembayaran & pelanggan</h2>
                    <p className="text-isi text-teks-sekunder">
                        Syarat pelanggan berlaku hanya bila kasir memilih pelanggan. Saat kasir offline, riwayat
                        pelanggan memakai data terakhir di perangkat; bila ternyata tidak memenuhi syarat, penjualan
                        tetap diterima dan masuk tinjauan.
                    </p>
                    {OpsiMetodeBayar.length > 0 ? (
                        <GrupCentang
                            legenda="Metode bayar (kosong = semua; semua pembayaran wajib memakai metode terpilih)"
                            opsi={OpsiMetodeBayar.map((o) => ({ nilai: o.Nilai, label: o.Label }))}
                            terpilih={isian.MetodeBayar}
                            saatBerubah={(terpilih) => Ubah({ MetodeBayar: terpilih })}
                            galat={galat.MetodeBayar}
                        />
                    ) : null}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <BidangPilihan
                            label="Ulang tahun pelanggan"
                            nilai={isian.UlangTahun}
                            opsi={[{ Nilai: '', Label: 'Tanpa syarat ulang tahun' }, ...OpsiUlangTahun]}
                            saatBerubah={(nilai) => Ubah({ UlangTahun: nilai as JenisUlangTahunPromo | '' })}
                            galat={galat.UlangTahun}
                        />
                        {isian.UlangTahun === 'Rentang' ? (
                            <BidangJumlah
                                label="Jarak dari hari ulang tahun (hari)"
                                nilai={isian.HariUlangTahun}
                                saatBerubah={(nilai) => Ubah({ HariUlangTahun: nilai })}
                                desimal={0}
                                digitBulat={2}
                                keterangan="Misal 7 = seminggu sebelum sampai seminggu sesudah (1–30)."
                                galat={galat.HariUlangTahun}
                                required
                            />
                        ) : null}
                    </div>
                    <KotakCentang
                        label="Hanya transaksi pertama pelanggan"
                        nilai={isian.TransaksiPertama}
                        saatBerubah={(nilai) => Ubah({ TransaksiPertama: nilai })}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <BidangJumlah
                            label="Batas pakai per pelanggan"
                            nilai={isian.BatasPerPelanggan}
                            saatBerubah={(nilai) => Ubah({ BatasPerPelanggan: nilai })}
                            desimal={0}
                            digitBulat={3}
                            keterangan="Kosong = tanpa batas. Diisi = hanya untuk pembeli dengan data pelanggan."
                            galat={galat.BatasPerPelanggan}
                        />
                        {isian.BatasPerPelanggan !== '' ? (
                            <BidangPilihan
                                label="Periode batas"
                                nilai={isian.PeriodeBatasPelanggan}
                                opsi={OpsiPeriodeBatas}
                                saatBerubah={(nilai) =>
                                    Ubah({ PeriodeBatasPelanggan: nilai as PeriodeBatasPelangganPromo })
                                }
                                required
                            />
                        ) : null}
                    </div>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={memproses}>
                        Simpan promo
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={alamat}>Batal</Link>
                    </Button>
                </div>
            </form>
        </TataLetakAplikasi>
    );
}
