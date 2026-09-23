/** Tagihan & pembayaran langganan (P-08). Bentuk = TagihanLanggananTenant::PetakanTagihan/PetakanPembayaran. */
export type StatusTagihanLangganan =
    'Draf' | 'Terbit' | 'JatuhTempo' | 'Lunas' | 'Dibatalkan' | 'Dihapuskan' | 'Dikembalikan';

export type TagihanLangganan = {
    Uuid: string;
    Nomor: string;
    Jenis: 'Aktivasi' | 'Perpanjangan';
    LabelJenis: string;
    Status: StatusTagihanLangganan;
    LabelStatus: string;
    KodePaket: string;
    NamaPaket: string;
    Siklus: 'Bulanan' | 'Tahunan';
    JumlahBulan: number;
    Subtotal: string;
    KodeKupon: string | null;
    Diskon: string;
    TarifPpn: string;
    PengaliDppPembilang: number;
    PengaliDppPenyebut: number;
    DasarPengenaanPajak: string;
    JumlahPpn: string;
    Total: string;
    TerbitPada: string;
    JatuhTempoPada: string;
    DibayarPada: string | null;
    DibatalkanPada: string | null;
    PeriodeMulai: string | null;
    PeriodeSelesai: string | null;
};

export type StatusPembayaranLangganan = 'Menunggu' | 'Diterima' | 'Ditolak';

export type PembayaranLangganan = {
    Uuid: string;
    Status: StatusPembayaranLangganan;
    LabelStatus: string;
    Jumlah: string;
    TanggalTransfer: string | null;
    BankPengirim: string | null;
    NamaPengirim: string | null;
    BankTujuan: string | null;
    NomorRekeningTujuan: string | null;
    NamaFileBukti: string | null;
    MimeBukti: string | null;
    DiunggahPada: string | null;
    DiverifikasiPada: string | null;
    AlasanTolak: string | null;
};

/** Jenis label status tagihan: warna hanya penguat teks (PRD §17.6.3). */
export function JenisLabelTagihan(status: StatusTagihanLangganan): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    switch (status) {
        case 'Lunas':
            return 'sukses';
        case 'Terbit':
            return 'peringatan';
        case 'JatuhTempo':
            return 'bahaya';
        default:
            return 'netral';
    }
}

export function JenisLabelPembayaran(status: StatusPembayaranLangganan): 'sukses' | 'peringatan' | 'bahaya' {
    switch (status) {
        case 'Diterima':
            return 'sukses';
        case 'Ditolak':
            return 'bahaya';
        default:
            return 'peringatan';
    }
}
