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

function RenderTagihan(bolehUnggah = true) {
    return render(
        <HalamanTagihanLangganan
            Tagihan={tagihan}
            Pembayaran={[]}
            RekeningTujuan={[{ Kode: 'BCA', NamaBank: 'BCA', NomorRekening: '1234567890', AtasNama: 'PT Kasir' }]}
            BolehUnggah={bolehUnggah}
            UkuranBuktiMaksimalKb={5120}
        />,
    );
}

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
});
