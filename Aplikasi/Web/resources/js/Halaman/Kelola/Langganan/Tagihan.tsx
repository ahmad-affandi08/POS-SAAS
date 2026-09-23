import { Link, router, useForm } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RincianTagihan from '@/Komponen/Langganan/RincianTagihan';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import { JenisLabelPembayaran, type PembayaranLangganan, type TagihanLangganan } from '@/Tipe/TagihanLangganan';

type Rekening = { Kode: string; NamaBank: string; NomorRekening: string; AtasNama: string };

type PropsTagihan = {
    Tagihan: TagihanLangganan;
    Pembayaran: PembayaranLangganan[];
    RekeningTujuan: Rekening[];
    BolehUnggah: boolean;
    UkuranBuktiMaksimalKb: number;
};

/** Detail tagihan, rekening tujuan, unggah bukti transfer, dan status verifikasi (P-08 langkah 3). */
export default function HalamanTagihanLangganan({
    Tagihan,
    Pembayaran,
    RekeningTujuan,
    BolehUnggah,
    UkuranBuktiMaksimalKb,
}: PropsTagihan) {
    const terbuka = Tagihan.Status === 'Terbit' || Tagihan.Status === 'JatuhTempo';
    const menunggu = Pembayaran.find((pembayaran) => pembayaran.Status === 'Menunggu');
    const [membatalkan, AturMembatalkan] = useState(false);

    const Batalkan = () => {
        if (!window.confirm(`Batalkan tagihan ${Tagihan.Nomor}? Anda bisa membuat tagihan baru setelahnya.`)) {
            return;
        }

        router.post(
            `/kelola/langganan/tagihan/${Tagihan.Uuid}/batalkan`,
            {},
            { onStart: () => AturMembatalkan(true), onFinish: () => AturMembatalkan(false) },
        );
    };

    return (
        <TataLetakAplikasi judul={`Tagihan ${Tagihan.Nomor}`}>
            <p>
                <Link href="/kelola/langganan" className="text-label font-semibold text-brand underline">
                    Kembali ke langganan
                </Link>
            </p>
            {menunggu ? (
                <Pemberitahuan jenis="info" judul="Bukti transfer sedang diverifikasi">
                    Kami memeriksa mutasi rekening pada hari kerja. Hasilnya dikirim ke email Anda.
                </Pemberitahuan>
            ) : null}
            {Tagihan.Status === 'Lunas' ? (
                <Pemberitahuan jenis="sukses" judul="Tagihan lunas">
                    Terima kasih. Langganan Anda sudah aktif sesuai periode di bawah.
                </Pemberitahuan>
            ) : null}
            <RincianTagihan tagihan={Tagihan} />
            {terbuka ? <DaftarRekening rekening={RekeningTujuan} total={Tagihan.Total} /> : null}
            {BolehUnggah ? (
                <FormBukti tagihan={Tagihan} rekening={RekeningTujuan} ukuranMaksimalKb={UkuranBuktiMaksimalKb} />
            ) : null}
            <RiwayatPembayaran pembayaran={Pembayaran} />
            {BolehUnggah ? (
                <div>
                    <Tombol varian="bahaya" memproses={membatalkan} onClick={Batalkan}>
                        Batalkan tagihan
                    </Tombol>
                </div>
            ) : null}
        </TataLetakAplikasi>
    );
}

function DaftarRekening({ rekening, total }: { rekening: Rekening[]; total: string }) {
    return (
        <section aria-labelledby="judul-rekening" className="rounded-panel border border-garis bg-permukaan px-4 py-3">
            <h2 id="judul-rekening" className="text-subjudul font-semibold text-teks-utama">
                Transfer ke rekening berikut
            </h2>
            <p className="text-isi text-teks-sekunder">
                Transfer tepat <span className="font-semibold tabular-nums text-teks-utama">{FormatRupiah(total)}</span>{' '}
                agar verifikasi cepat.
            </p>
            {rekening.length === 0 ? (
                <p className="mt-2 text-isi text-bahaya">Rekening tujuan belum diatur. Hubungi tim kami.</p>
            ) : (
                <ul className="mt-2 flex flex-col gap-2">
                    {rekening.map((baris) => (
                        <li key={baris.Kode} className="text-isi">
                            <span className="font-semibold text-teks-utama">{baris.NamaBank}</span>{' '}
                            <span className="font-mono">{baris.NomorRekening}</span>{' '}
                            <span className="text-teks-sekunder">a.n. {baris.AtasNama}</span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function FormBukti({
    tagihan,
    rekening,
    ukuranMaksimalKb,
}: {
    tagihan: TagihanLangganan;
    rekening: Rekening[];
    ukuranMaksimalKb: number;
}) {
    const idBerkas = useId();
    const formulir = useForm<{
        Bukti: File | null;
        Jumlah: string;
        TanggalTransfer: string;
        BankPengirim: string;
        NamaPengirim: string;
        KodeRekeningTujuan: string;
    }>({
        Bukti: null,
        Jumlah: tagihan.Total.replace(/\.00$/, ''),
        TanggalTransfer: '',
        BankPengirim: '',
        NamaPengirim: '',
        KodeRekeningTujuan: rekening[0]?.Kode ?? '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/kelola/langganan/tagihan/${tagihan.Uuid}/pembayaran`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => formulir.reset(),
        });
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-4 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">Unggah bukti transfer</h2>
            <div className="flex flex-col gap-1 sm:col-span-2">
                <label htmlFor={idBerkas} className="text-label font-semibold text-teks-utama">
                    Bukti transfer
                </label>
                <input
                    id={idBerkas}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    onChange={(peristiwa) => formulir.setData('Bukti', peristiwa.target.files?.[0] ?? null)}
                    aria-invalid={formulir.errors.Bukti ? true : undefined}
                    aria-describedby={`${idBerkas}-keterangan`}
                    className="text-isi text-teks-utama file:mr-3 file:h-10 file:rounded-kontrol file:border file:border-garis-input file:bg-permukaan file:px-4 file:text-label file:font-semibold"
                />
                <p id={`${idBerkas}-keterangan`} className="text-keterangan text-teks-sekunder">
                    Foto atau PDF (JPG, PNG, WEBP, PDF), maksimal {Math.floor(ukuranMaksimalKb / 1024)} MB.
                </p>
                {formulir.errors.Bukti ? (
                    <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.Bukti}</p>
                ) : null}
            </div>
            <BidangTeks
                label="Jumlah transfer (Rp)"
                inputMode="decimal"
                nilai={formulir.data.Jumlah}
                saatBerubah={(nilai) => formulir.setData('Jumlah', nilai)}
                galat={formulir.errors.Jumlah}
                keterangan={`Harus sama dengan total ${FormatRupiah(tagihan.Total)}.`}
            />
            <BidangTeks
                label="Tanggal transfer (TTTT-BB-HH)"
                kode
                nilai={formulir.data.TanggalTransfer}
                saatBerubah={(nilai) => formulir.setData('TanggalTransfer', nilai)}
                galat={formulir.errors.TanggalTransfer}
            />
            <BidangTeks
                label="Bank pengirim"
                nilai={formulir.data.BankPengirim}
                saatBerubah={(nilai) => formulir.setData('BankPengirim', nilai)}
                galat={formulir.errors.BankPengirim}
            />
            <BidangTeks
                label="Nama pemilik rekening pengirim"
                nilai={formulir.data.NamaPengirim}
                saatBerubah={(nilai) => formulir.setData('NamaPengirim', nilai)}
                galat={formulir.errors.NamaPengirim}
            />
            {rekening.length > 1 ? (
                <BidangPilihan
                    label="Rekening tujuan"
                    nilai={formulir.data.KodeRekeningTujuan}
                    opsi={rekening.map((baris) => ({
                        Nilai: baris.Kode,
                        Label: `${baris.NamaBank} ${baris.NomorRekening}`,
                    }))}
                    saatBerubah={(nilai) => formulir.setData('KodeRekeningTujuan', nilai)}
                    galat={formulir.errors.KodeRekeningTujuan}
                />
            ) : null}
            <div className="sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing} disabled={formulir.data.Bukti === null}>
                    Kirim bukti transfer
                </Tombol>
            </div>
        </form>
    );
}

function RiwayatPembayaran({ pembayaran }: { pembayaran: PembayaranLangganan[] }) {
    if (pembayaran.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="judul-pembayaran" className="flex flex-col gap-2">
            <h2 id="judul-pembayaran" className="text-subjudul font-semibold text-teks-utama">
                Bukti transfer terkirim
            </h2>
            <div className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                <table className="w-full text-left text-isi">
                    <caption className="sr-only">Riwayat bukti transfer</caption>
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Diunggah
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Transfer
                            </th>
                            <th scope="col" className="px-4 py-2 text-right font-semibold">
                                Jumlah
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                Status
                            </th>
                            <th scope="col" className="px-4 py-2 font-semibold">
                                <span className="sr-only">Bukti</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {pembayaran.map((baris) => (
                            <tr key={baris.Uuid} className="border-b border-garis align-top last:border-b-0">
                                <td className="px-4 py-2">{FormatTanggalWaktu(baris.DiunggahPada)}</td>
                                <td className="px-4 py-2">
                                    <span className="block">{FormatTanggal(baris.TanggalTransfer)}</span>
                                    <span className="block text-keterangan text-teks-sekunder">
                                        {baris.BankPengirim} · {baris.NamaPengirim}
                                    </span>
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums">{FormatRupiah(baris.Jumlah)}</td>
                                <td className="px-4 py-2">
                                    <LabelStatus jenis={JenisLabelPembayaran(baris.Status)} teks={baris.LabelStatus} />
                                    {baris.AlasanTolak ? (
                                        <span className="mt-1 block text-keterangan text-teks-sekunder">
                                            Alasan: {baris.AlasanTolak}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-4 py-2 text-right">
                                    <a
                                        href={`/kelola/langganan/pembayaran/${baris.Uuid}/bukti`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-label font-semibold text-brand underline"
                                    >
                                        Lihat bukti
                                    </a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
