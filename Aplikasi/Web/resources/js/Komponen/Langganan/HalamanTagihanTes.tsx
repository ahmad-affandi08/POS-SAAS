import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanTagihanLangganan from '@/Halaman/Kelola/Langganan/Tagihan';
import { AturHalamanUji, kirimanForm, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import type { TagihanLangganan } from '@/Tipe/TagihanLangganan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const tagihan: TagihanLangganan = {
    Uuid: 'TG-1',
    Nomor: 'INV-2026-0001',
    Jenis: 'Aktivasi',
    LabelJenis: 'Aktivasi',
    Status: 'Terbit',
    LabelStatus: 'Menunggu pembayaran',
    KodePaket: 'PRO',
    NamaPaket: 'Pro',
    Siklus: 'Bulanan',
    JumlahBulan: 1,
    Subtotal: '200000.00',
    KodeKupon: null,
    Diskon: '0.00',
    TarifPpn: '12.000000',
    PengaliDppPembilang: 11,
    PengaliDppPenyebut: 12,
    DasarPengenaanPajak: '183333.00',
    JumlahPpn: '22000.00',
    Total: '222000.00',
    TerbitPada: '2026-09-20T02:00:00Z',
    JatuhTempoPada: '2026-09-27T02:00:00Z',
    DibayarPada: null,
    DibatalkanPada: null,
    PeriodeMulai: null,
    PeriodeSelesai: null,
};

const gerbangAktif = { KunciKlien: 'SB-Mid-client-abc', UrlSnapJs: 'https://app.sandbox.midtrans.com/snap/snap.js' };

function RenderTagihan(bolehUnggah = true, tambahan: Partial<PropsRender> = {}) {
    return render(
        <HalamanTagihanLangganan
            Tagihan={tagihan}
            Pembayaran={[]}
            RekeningTujuan={[{ Kode: 'BCA', NamaBank: 'BCA', NomorRekening: '1234567890', AtasNama: 'PT Kasir' }]}
            BolehUnggah={bolehUnggah}
            BolehBayarOnline={tambahan.BolehBayarOnline ?? false}
            BolehBatalkan={tambahan.BolehBatalkan ?? bolehUnggah}
            Gerbang={tambahan.Gerbang ?? null}
            UkuranBuktiMaksimalKb={5120}
        />,
    );
}

type PropsRender = {
    BolehBayarOnline: boolean;
    BolehBatalkan: boolean;
    Gerbang: { KunciKlien: string; UrlSnapJs: string } | null;
};

describe('Langganan/Tagihan (P-08): dialog unggah bukti & konfirmasi batal', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/langganan/tagihan/TG-1'));
    afterEach(() => cleanup());

    it('formulir bukti transfer ada di dialog; kirim ke alamat pembayaran tagihan', () => {
        RenderTagihan();

        expect(screen.queryByLabelText('Bukti transfer')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Unggah bukti transfer' }));

        const dialog = screen.getByRole('dialog', { name: 'Unggah bukti transfer' });
        expect(dialog).toBeTruthy();
        const kirim = screen.getByRole('button', { name: 'Kirim bukti transfer' }) as HTMLButtonElement;
        expect(kirim.disabled).toBe(true);

        const berkas = new File(['isi'], 'bukti.png', { type: 'image/png' });
        fireEvent.change(screen.getByLabelText('Bukti transfer'), { target: { files: [berkas] } });
        fireEvent.change(screen.getByLabelText('Bank pengirim'), { target: { value: 'BRI' } });
        expect(kirim.disabled).toBe(false);
        fireEvent.click(kirim);

        expect(kirimanForm).toHaveLength(1);
        expect(kirimanForm[0]?.url).toBe('/kelola/langganan/tagihan/TG-1/pembayaran');
        expect(kirimanForm[0]?.data).toMatchObject({
            Bukti: berkas,
            Jumlah: '222000',
            BankPengirim: 'BRI',
            KodeRekeningTujuan: 'BCA',
        });
    });

    it('batalkan tagihan hanya setelah dikonfirmasi', () => {
        RenderTagihan();

        fireEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));
        expect(screen.getByRole('alertdialog', { name: 'Batalkan tagihan INV-2026-0001?' })).toBeTruthy();
        expect(tiruanRouter.post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Jangan batalkan' }));
        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(tiruanRouter.post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));
        fireEvent.click(screen.getByRole('button', { name: 'Batalkan tagihan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/langganan/tagihan/TG-1/batalkan',
            {},
            expect.anything(),
        );
    });

    it('tanpa izin unggah: tidak ada tombol unggah maupun batalkan', () => {
        RenderTagihan(false);

        expect(screen.queryByRole('button', { name: 'Unggah bukti transfer' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Batalkan tagihan' })).toBeNull();
        expect(screen.getByText('Transfer ke rekening berikut')).toBeTruthy();
    });
    it('BR-P08.11: tombol bayar online hanya muncul saat gerbang billing aktif', () => {
        RenderTagihan(true, { BolehBayarOnline: true, Gerbang: gerbangAktif });

        expect(screen.getByRole('button', { name: 'Bayar online' })).toBeTruthy();
        // Transfer manual tetap tersedia: tenant memilih, bukan dipaksa salah satu.
        expect(screen.getByRole('button', { name: 'Unggah bukti transfer' })).toBeTruthy();
    });

    it('BR-P08.11: gerbang belum dikonfigurasi = tidak ada tombol bayar online', () => {
        RenderTagihan(true, { BolehBayarOnline: true, Gerbang: null });

        expect(screen.queryByRole('button', { name: 'Bayar online' })).toBeNull();
    });

    it('percobaan bayar online yang tertunda memblokir pembatalan, tetapi tidak memblokir unggah bukti', () => {
        // Cerminan aturan server: `BatalkanTagihanLangganan` menolak selama ada pembayaran `Menunggu` apa pun,
        // sedangkan BR-P08.8 hanya melarang bukti transfer kedua.
        RenderTagihan(true, { BolehBatalkan: false, BolehBayarOnline: false });

        expect(screen.queryByRole('button', { name: 'Batalkan tagihan' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Unggah bukti transfer' })).toBeTruthy();
    });
});
