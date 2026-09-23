import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import { JenisLabelTagihan, type TagihanLangganan } from '@/Tipe/TagihanLangganan';

type PropsRincianTagihan = { tagihan: TagihanLangganan; namaTenant?: string };

/**
 * Rincian satu tagihan langganan (P-08): subtotal → diskon kupon → DPP → PPN → total. Angka dari server, tidak
 * dihitung di browser. Dipakai back-office tenant dan Platform Pengelola.
 */
export default function RincianTagihan({ tagihan, namaTenant }: PropsRincianTagihan) {
    const adaPpn = tagihan.JumlahPpn !== '0.00' || tagihan.DasarPengenaanPajak !== '0.00';
    const pengaliPenuh = tagihan.PengaliDppPembilang === tagihan.PengaliDppPenyebut;

    return (
        <section aria-labelledby="judul-rincian" className="rounded-panel border border-garis bg-permukaan">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-garis px-4 py-3">
                <div>
                    <h2 id="judul-rincian" className="font-mono text-subjudul font-semibold text-teks-utama">
                        {tagihan.Nomor}
                    </h2>
                    <p className="text-keterangan text-teks-sekunder">
                        {namaTenant ? `${namaTenant} · ` : ''}
                        {tagihan.LabelJenis}
                    </p>
                </div>
                <LabelStatus jenis={JenisLabelTagihan(tagihan.Status)} teks={tagihan.LabelStatus} />
            </div>
            <dl className="grid gap-x-6 gap-y-2 px-4 py-3 text-isi sm:grid-cols-2">
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Terbit</dt>
                    <dd>{FormatTanggalWaktu(tagihan.TerbitPada)}</dd>
                </div>
                <div>
                    <dt className="text-keterangan text-teks-sekunder">Jatuh tempo</dt>
                    <dd>{FormatTanggalWaktu(tagihan.JatuhTempoPada)}</dd>
                </div>
                {tagihan.DibayarPada ? (
                    <div>
                        <dt className="text-keterangan text-teks-sekunder">Lunas</dt>
                        <dd>{FormatTanggalWaktu(tagihan.DibayarPada)}</dd>
                    </div>
                ) : null}
                {tagihan.PeriodeMulai && tagihan.PeriodeSelesai ? (
                    <div>
                        <dt className="text-keterangan text-teks-sekunder">Periode layanan</dt>
                        <dd>
                            {FormatTanggalWaktu(tagihan.PeriodeMulai)} – {FormatTanggalWaktu(tagihan.PeriodeSelesai)}
                        </dd>
                    </div>
                ) : null}
            </dl>
            <table className="w-full border-t border-garis text-left text-isi">
                <caption className="sr-only">Rincian biaya tagihan {tagihan.Nomor}</caption>
                <tbody>
                    <tr className="border-b border-garis">
                        <th scope="row" className="px-4 py-2 font-normal">
                            Paket {tagihan.NamaPaket} · {tagihan.Siklus === 'Tahunan' ? '12 bulan' : '1 bulan'}
                        </th>
                        <td className="px-4 py-2 text-right tabular-nums">{FormatRupiah(tagihan.Subtotal)}</td>
                    </tr>
                    {tagihan.Diskon !== '0.00' ? (
                        <tr className="border-b border-garis">
                            <th scope="row" className="px-4 py-2 font-normal">
                                Diskon kupon <span className="font-mono">{tagihan.KodeKupon}</span>
                            </th>
                            <td className="px-4 py-2 text-right tabular-nums">−{FormatRupiah(tagihan.Diskon)}</td>
                        </tr>
                    ) : null}
                    {adaPpn ? (
                        <>
                            <tr className="border-b border-garis text-teks-sekunder">
                                <th scope="row" className="px-4 py-2 font-normal">
                                    Dasar pengenaan pajak
                                    {pengaliPenuh
                                        ? ''
                                        : ` (nilai lain ${tagihan.PengaliDppPembilang}/${tagihan.PengaliDppPenyebut})`}
                                </th>
                                <td className="px-4 py-2 text-right tabular-nums">
                                    {FormatRupiah(tagihan.DasarPengenaanPajak)}
                                </td>
                            </tr>
                            <tr className="border-b border-garis">
                                <th scope="row" className="px-4 py-2 font-normal">
                                    PPN {FormatPersen(tagihan.TarifPpn)}%
                                </th>
                                <td className="px-4 py-2 text-right tabular-nums">{FormatRupiah(tagihan.JumlahPpn)}</td>
                            </tr>
                        </>
                    ) : null}
                    <tr>
                        <th scope="row" className="px-4 py-3 font-semibold">
                            Total tagihan
                        </th>
                        <td className="px-4 py-3 text-right text-subjudul font-bold tabular-nums">
                            {FormatRupiah(tagihan.Total)}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    );
}
