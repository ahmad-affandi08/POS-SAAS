import { Link, useForm, usePage } from '@inertiajs/react';
import { useId, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RincianTagihan from '@/Komponen/Langganan/RincianTagihan';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';
import { JenisLabelPembayaran, type PembayaranLangganan, type TagihanLangganan } from '@/Tipe/TagihanLangganan';

type BarisPembayaran = PembayaranLangganan & { JumlahDiterima: string | null; Verifikator: string | null };

type PropsDetailTagihan = {
    Tagihan: TagihanLangganan & { NamaTenant: string };
    Pembayaran: BarisPembayaran[];
};

/** Detail tagihan & verifikasi bukti transfer (P-08 langkah 3). Terima hanya bila jumlah di rekening cocok. */
export default function HalamanDetailTagihan({ Tagihan, Pembayaran }: PropsDetailTagihan) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehVerifikasi = PunyaIzin(props.Pengguna, IzinPengelola.TagihanVerifikasi);

    return (
        <TataLetakPengelola judul={`Tagihan ${Tagihan.Nomor}`}>
            <p>
                <Link href="/tagihan" className="text-label font-semibold underline">
                    Kembali ke daftar tagihan
                </Link>
            </p>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            <RincianTagihan tagihan={Tagihan} namaTenant={Tagihan.NamaTenant} />
            <section aria-labelledby="judul-pembayaran" className="flex flex-col gap-3">
                <h2 id="judul-pembayaran" className="text-subjudul font-semibold text-teks-utama">
                    Pembayaran
                </h2>
                {Pembayaran.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Tenant belum mengunggah bukti transfer.</p>
                ) : (
                    Pembayaran.map((baris) => (
                        <KartuPembayaran
                            key={baris.Uuid}
                            pembayaran={baris}
                            total={Tagihan.Total}
                            bolehVerifikasi={bolehVerifikasi}
                        />
                    ))
                )}
            </section>
        </TataLetakPengelola>
    );
}

function KartuPembayaran({
    pembayaran,
    total,
    bolehVerifikasi,
}: {
    pembayaran: BarisPembayaran;
    total: string;
    bolehVerifikasi: boolean;
}) {
    return (
        <article className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <LabelStatus jenis={JenisLabelPembayaran(pembayaran.Status)} teks={pembayaran.LabelStatus} />
                <a
                    href={`/tagihan/pembayaran/${pembayaran.Uuid}/bukti`}
                    target="_blank"
                    rel="noreferrer"
                    className="text-label font-semibold underline"
                >
                    Buka bukti transfer
                </a>
            </div>
            <dl className="grid gap-x-6 gap-y-2 text-isi sm:grid-cols-3">
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Jumlah menurut tenant</dt>
                    <dd className="tabular-nums">{FormatRupiah(pembayaran.Jumlah)}</dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Tanggal transfer</dt>
                    <dd>{FormatTanggal(pembayaran.TanggalTransfer)}</dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Diunggah</dt>
                    <dd>{FormatTanggalWaktu(pembayaran.DiunggahPada)}</dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Pengirim</dt>
                    <dd>
                        {pembayaran.BankPengirim} · {pembayaran.NamaPengirim}
                    </dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Rekening tujuan</dt>
                    <dd>
                        {pembayaran.BankTujuan} <span className="font-mono">{pembayaran.NomorRekeningTujuan}</span>
                    </dd>
                </div>
                {pembayaran.DiverifikasiPada ? (
                    <div>
                        <dt className="text-keterangan text-teks-sekunder">Diverifikasi</dt>
                        <dd>
                            {FormatTanggalWaktu(pembayaran.DiverifikasiPada)} oleh {pembayaran.Verifikator ?? '—'}
                        </dd>
                    </div>
                ) : null}
                {pembayaran.AlasanTolak ? (
                    <div className="sm:col-span-3">
                        <dt className="text-keterangan text-teks-sekunder">Alasan ditolak</dt>
                        <dd>{pembayaran.AlasanTolak}</dd>
                    </div>
                ) : null}
            </dl>
            {pembayaran.Status === 'Menunggu' && bolehVerifikasi ? (
                <div className="grid gap-4 border-t border-garis pt-3 lg:grid-cols-2">
                    <FormTerima uuid={pembayaran.Uuid} total={total} />
                    <FormTolak uuid={pembayaran.Uuid} />
                </div>
            ) : null}
        </article>
    );
}

function FormTerima({ uuid, total }: { uuid: string; total: string }) {
    const formulir = useForm({ JumlahDiterima: '', Catatan: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tagihan/pembayaran/${uuid}/terima`, { preserveScroll: true });
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-3" noValidate>
            <h3 className="text-label font-semibold text-teks-utama">Terima pembayaran</h3>
            <BidangTeks
                label="Jumlah masuk di mutasi rekening (Rp)"
                inputMode="decimal"
                nilai={formulir.data.JumlahDiterima}
                saatBerubah={(nilai) => formulir.setData('JumlahDiterima', nilai)}
                galat={formulir.errors.JumlahDiterima}
                keterangan={`Harus sama dengan total tagihan ${FormatRupiah(total)}.`}
            />
            <BidangTeks
                label="Catatan (opsional)"
                nilai={formulir.data.Catatan}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
            />
            <div>
                <Tombol type="submit" memproses={formulir.processing}>
                    Terima dan aktifkan langganan
                </Tombol>
            </div>
        </form>
    );
}

function FormTolak({ uuid }: { uuid: string }) {
    const id = useId();
    const formulir = useForm({ Alasan: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tagihan/pembayaran/${uuid}/tolak`, { preserveScroll: true });
    };

    return (
        <form onSubmit={Kirim} className="flex flex-col gap-3" noValidate>
            <h3 className="text-label font-semibold text-teks-utama">Tolak bukti</h3>
            <div className="flex flex-col gap-1">
                <label htmlFor={id} className="text-label font-semibold text-teks-utama">
                    Alasan (dikirim ke pemilik usaha)
                </label>
                <textarea
                    id={id}
                    rows={3}
                    value={formulir.data.Alasan}
                    onChange={(peristiwa) => formulir.setData('Alasan', peristiwa.target.value)}
                    aria-invalid={formulir.errors.Alasan ? true : undefined}
                    className={`rounded-kontrol border bg-permukaan px-3 py-2 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                        formulir.errors.Alasan ? 'border-bahaya' : 'border-garis-input'
                    }`}
                />
                {formulir.errors.Alasan ? (
                    <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.Alasan}</p>
                ) : null}
            </div>
            <div>
                <Tombol type="submit" varian="bahaya" memproses={formulir.processing}>
                    Tolak bukti transfer
                </Tombol>
            </div>
        </form>
    );
}
