import { useForm } from '@inertiajs/react';
import { useRef, type FormEvent } from 'react';

import BidangGambar from '@/Komponen/Formulir/BidangGambar';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangTeksPanjang from '@/Komponen/Formulir/BidangTeksPanjang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { AlamatPanduan, type PropsProfilUsaha } from '@/Tipe/PanduanAwal';

type IsianProfilUsaha = {
    NamaUsaha: string;
    Alamat: string;
    KodeKota: string;
    Npwp: string;
    Pkp: boolean;
    Logo: File | null;
    HapusLogo: boolean;
};

/** Langkah 1 F-01: nama usaha, alamat & kota outlet (zona waktu ikut kota), NPWP/PKP, dan logo. */
export default function HalamanProfilUsaha({ Progres, Profil, Kota, BatasLogo }: PropsProfilUsaha) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const formulir = useForm<IsianProfilUsaha>({
        NamaUsaha: Profil.NamaUsaha,
        Alamat: Profil.Alamat ?? '',
        KodeKota: Profil.KodeKota ?? '',
        Npwp: Profil.Npwp ?? '',
        Pkp: Profil.Pkp,
        Logo: null,
        HapusLogo: false,
    });
    const kotaTerpilih = Kota.find((baris) => baris.Kode === formulir.data.KodeKota);
    const pilihanKota = Kota.map((baris) => ({
        Nilai: baris.Kode,
        Label: `${baris.Nama}${baris.NamaProvinsi ? `, ${baris.NamaProvinsi}` : ''}`,
    }));

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(AlamatPanduan.ProfilUsaha, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    return (
        <TataLetakPanduan progres={Progres} langkah="ProfilUsaha">
            <Card className="p-4 sm:p-6">
                <form ref={elemenFormulir} onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                    <RingkasanGalatFormulir galat={formulir.errors} />
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <BidangTeks
                            label="Nama usaha"
                            nilai={formulir.data.NamaUsaha}
                            saatBerubah={(nilai) => formulir.setData('NamaUsaha', nilai)}
                            galat={formulir.errors.NamaUsaha}
                            keterangan="Tampil di struk dan aplikasi kasir, misal Kopi Nusantara."
                            maxLength={150}
                            autoComplete="organization"
                            required
                        />
                        <div className="flex flex-col gap-1">
                            <BidangPilihan
                                label="Kabupaten/kota outlet"
                                nilai={formulir.data.KodeKota}
                                opsi={pilihanKota}
                                saatBerubah={(nilai) => formulir.setData('KodeKota', nilai)}
                                galat={formulir.errors.KodeKota}
                                required
                                kosong={Kota.length === 0 ? 'Data wilayah belum tersedia' : 'Pilih kabupaten/kota'}
                            />
                            <p className="text-keterangan text-teks-sekunder">
                                {kotaTerpilih
                                    ? `Zona waktu outlet mengikuti kota: ${kotaTerpilih.ZonaWaktu}. Tarif PBJT juga mengikuti kota.`
                                    : 'Zona waktu dan tarif PBJT outlet mengikuti kota.'}
                            </p>
                        </div>
                        <div className="md:col-span-2">
                            <BidangTeksPanjang
                                label="Alamat outlet (opsional)"
                                nilai={formulir.data.Alamat}
                                saatBerubah={(nilai) => formulir.setData('Alamat', nilai)}
                                galat={formulir.errors.Alamat}
                                keterangan="Dicetak di struk. Contoh: Jl. Kaliurang Km 5 No. 12, Sleman."
                                baris={3}
                                maksimal={500}
                            />
                        </div>
                    </div>

                    <FieldSet className="gap-2">
                        <FieldLegend variant="label" className="mb-0 text-label font-semibold text-teks-utama">
                            Status pajak usaha
                        </FieldLegend>
                        <KotakCentang
                            label="Usaha saya PKP (Pengusaha Kena Pajak)"
                            nilai={formulir.data.Pkp}
                            saatBerubah={(nilai) => formulir.setData('Pkp', nilai)}
                        />
                        <div className="max-w-md">
                            <BidangTeks
                                label={formulir.data.Pkp ? 'NPWP' : 'NPWP (opsional)'}
                                nilai={formulir.data.Npwp}
                                saatBerubah={(nilai) => formulir.setData('Npwp', nilai)}
                                galat={formulir.errors.Npwp}
                                required={formulir.data.Pkp}
                                keterangan={
                                    formulir.data.Pkp
                                        ? '15 atau 16 angka. Wajib untuk usaha PKP. Titik dan tanda hubung boleh diketik.'
                                        : '15 atau 16 angka. Titik dan tanda hubung boleh diketik.'
                                }
                                inputMode="numeric"
                                maxLength={24}
                                kode
                            />
                        </div>
                    </FieldSet>

                    <BidangGambar
                        label="Logo usaha (opsional)"
                        berkas={formulir.data.Logo}
                        saatBerubah={(berkas) => formulir.setData({ ...formulir.data, Logo: berkas, HapusLogo: false })}
                        tautanSaatIni={formulir.data.HapusLogo ? null : Profil.TautanLogo}
                        saatHapusSaatIni={() => formulir.setData({ ...formulir.data, Logo: null, HapusLogo: true })}
                        labelHapus="Hapus logo"
                        ukuranMaksimalKb={BatasLogo.UkuranMaksimalKb}
                        ekstensi={BatasLogo.Ekstensi}
                        keterangan="Dipakai di struk dan aplikasi kasir. Gambar persegi paling rapi."
                        galat={formulir.errors.Logo}
                    />
                    {formulir.data.HapusLogo ? (
                        <p className="text-keterangan text-teks-sekunder">
                            Logo akan dihapus saat profil disimpan.{' '}
                            <Button
                                type="button"
                                variant="link"
                                onClick={() => formulir.setData('HapusLogo', false)}
                                className="h-auto p-0 text-keterangan font-semibold underline"
                            >
                                Batalkan hapus logo
                            </Button>
                        </p>
                    ) : null}

                    <div>
                        <Tombol type="submit" memproses={formulir.processing}>
                            Simpan profil usaha
                        </Tombol>
                    </div>
                </form>
            </Card>
        </TataLetakPanduan>
    );
}
