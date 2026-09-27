import 'UraiJson.dart';

/// Modul Gudang (POS-25, `GET /api/pos/v1/gudang/*`): dokumen persediaan yang dikerjakan staf gudang di lokasi stok
/// outlet perangkat. Jumlah string desimal (4 angka di belakang titik); baris dirujuk lewat `urutan` per dokumen.
///
/// Satu baris PO yang bisa diterima. Jumlah dalam satuan PO ([simbolSatuan], isi [konversi] satuan dasar).
class BarisPesananGudang {
  const BarisPesananGudang({
    required this.urutan,
    required this.uuidProduk,
    required this.namaProduk,
    required this.sku,
    required this.simbolSatuan,
    required this.konversi,
    required this.pelacakan,
    required this.jumlah,
    required this.jumlahDiterima,
    required this.sisa,
  });

  final int urutan;
  final String? uuidProduk;
  final String namaProduk;
  final String? sku;
  final String simbolSatuan;
  final String konversi;

  /// `Tidak` / `Batch` / `Seri`.
  final String pelacakan;
  final String jumlah;
  final String jumlahDiterima;
  final String sisa;

  static BarisPesananGudang DariJson(Map<String, Object?> json) => BarisPesananGudang(
    urutan: UraiJson.AmbilBulat(json['Urutan']),
    uuidProduk: UraiJson.AmbilTeksAtauNull(json['UuidProduk']),
    namaProduk: UraiJson.AmbilTeks(json['NamaProduk']),
    sku: UraiJson.AmbilTeksAtauNull(json['Sku']),
    simbolSatuan: UraiJson.AmbilTeks(json['SimbolSatuan']),
    konversi: UraiJson.AmbilDesimal(json['Konversi'], '1'),
    pelacakan: UraiJson.AmbilTeks(json['Pelacakan'], 'Tidak'),
    jumlah: UraiJson.AmbilDesimal(json['Jumlah']),
    jumlahDiterima: UraiJson.AmbilDesimal(json['JumlahDiterima']),
    sisa: UraiJson.AmbilDesimal(json['Sisa']),
  );
}

/// PO yang siap diterima (Disetujui / Diterima sebagian).
class PesananGudangPos {
  const PesananGudangPos({
    required this.uuid,
    required this.nomor,
    required this.tanggal,
    required this.perkiraanTiba,
    required this.status,
    required this.labelStatus,
    required this.namaPemasok,
    required this.namaGudang,
    required this.baris,
  });

  final String uuid;
  final String nomor;
  final String tanggal;
  final String? perkiraanTiba;
  final String status;
  final String labelStatus;
  final String namaPemasok;
  final String namaGudang;
  final List<BarisPesananGudang> baris;

  static PesananGudangPos DariJson(Map<String, Object?> json) => PesananGudangPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    tanggal: UraiJson.AmbilTeks(json['Tanggal']),
    perkiraanTiba: UraiJson.AmbilTeksAtauNull(json['PerkiraanTiba']),
    status: UraiJson.AmbilTeks(json['Status']),
    labelStatus: UraiJson.AmbilTeks(json['LabelStatus']),
    namaPemasok: UraiJson.AmbilTeks(json['NamaPemasok']),
    namaGudang: UraiJson.AmbilTeks(json['NamaGudang']),
    baris: [for (final b in UraiJson.AmbilDaftarPeta(json['Baris'])) BarisPesananGudang.DariJson(b)],
  );
}

/// Hasil `POST gudang/penerimaan`: nomor GRN dan PO terbaru (null bila sudah diterima penuh).
class HasilPenerimaanGudang {
  const HasilPenerimaanGudang({required this.uuid, required this.nomor, required this.pesanan});

  final String uuid;
  final String nomor;
  final PesananGudangPos? pesanan;

  static HasilPenerimaanGudang DariJson(Map<String, Object?> json) {
    final penerimaan = UraiJson.AmbilPeta(json['Penerimaan']);
    final pesanan = UraiJson.AmbilPetaAtauNull(json['Pesanan']);
    return HasilPenerimaanGudang(
      uuid: UraiJson.AmbilTeks(penerimaan['Uuid']),
      nomor: UraiJson.AmbilTeks(penerimaan['Nomor']),
      pesanan: pesanan == null ? null : PesananGudangPos.DariJson(pesanan),
    );
  }
}

/// Satu baris transfer masuk (satuan dasar).
class BarisTransferGudang {
  const BarisTransferGudang({
    required this.urutan,
    required this.uuidProduk,
    required this.namaProduk,
    required this.sku,
    required this.simbolSatuan,
    required this.bolehDesimal,
    required this.nomorBatch,
    required this.tanggalKedaluwarsa,
    required this.nomorSeri,
    required this.jumlahDikirim,
    required this.jumlahDiterima,
    required this.sisa,
  });

  final int urutan;
  final String? uuidProduk;
  final String namaProduk;
  final String? sku;
  final String simbolSatuan;
  final bool bolehDesimal;
  final String? nomorBatch;
  final String? tanggalKedaluwarsa;
  final String? nomorSeri;
  final String jumlahDikirim;
  final String jumlahDiterima;
  final String sisa;

  static BarisTransferGudang DariJson(Map<String, Object?> json) => BarisTransferGudang(
    urutan: UraiJson.AmbilBulat(json['Urutan']),
    uuidProduk: UraiJson.AmbilTeksAtauNull(json['UuidProduk']),
    namaProduk: UraiJson.AmbilTeks(json['NamaProduk']),
    sku: UraiJson.AmbilTeksAtauNull(json['Sku']),
    simbolSatuan: UraiJson.AmbilTeks(json['SimbolSatuan']),
    bolehDesimal: UraiJson.AmbilBenar(json['BolehDesimal']),
    nomorBatch: UraiJson.AmbilTeksAtauNull(json['NomorBatch']),
    tanggalKedaluwarsa: UraiJson.AmbilTeksAtauNull(json['TanggalKedaluwarsa']),
    nomorSeri: UraiJson.AmbilTeksAtauNull(json['NomorSeri']),
    jumlahDikirim: UraiJson.AmbilDesimal(json['JumlahDikirim']),
    jumlahDiterima: UraiJson.AmbilDesimal(json['JumlahDiterima']),
    sisa: UraiJson.AmbilDesimal(json['Sisa']),
  );
}

/// Transfer stok yang sedang dalam perjalanan ke lokasi outlet perangkat.
class TransferGudangPos {
  const TransferGudangPos({
    required this.uuid,
    required this.nomor,
    required this.tanggal,
    required this.status,
    required this.labelStatus,
    required this.namaAsal,
    required this.namaTujuan,
    required this.catatan,
    required this.baris,
  });

  final String uuid;
  final String nomor;
  final String tanggal;

  /// `Dikirim` / `DiterimaSebagian` / `Diterima`.
  final String status;
  final String labelStatus;
  final String namaAsal;
  final String namaTujuan;
  final String? catatan;
  final List<BarisTransferGudang> baris;

  static TransferGudangPos DariJson(Map<String, Object?> json) => TransferGudangPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    tanggal: UraiJson.AmbilTeks(json['Tanggal']),
    status: UraiJson.AmbilTeks(json['Status']),
    labelStatus: UraiJson.AmbilTeks(json['LabelStatus']),
    namaAsal: UraiJson.AmbilTeks(json['NamaAsal']),
    namaTujuan: UraiJson.AmbilTeks(json['NamaTujuan']),
    catatan: UraiJson.AmbilTeksAtauNull(json['Catatan']),
    baris: [for (final b in UraiJson.AmbilDaftarPeta(json['Baris'])) BarisTransferGudang.DariJson(b)],
  );
}

/// Satu baris lembar hitung stok opname (satuan dasar). [jumlahSistem] null untuk opname hitung buta; [jumlahFisik]
/// null = belum dihitung.
class BarisOpnameGudang {
  const BarisOpnameGudang({
    required this.urutan,
    required this.uuidProduk,
    required this.namaProduk,
    required this.sku,
    required this.simbolSatuan,
    required this.bolehDesimal,
    required this.pelacakan,
    required this.nomorBatch,
    required this.tanggalKedaluwarsa,
    required this.nomorSeri,
    required this.jumlahSistem,
    required this.jumlahFisik,
  });

  final int urutan;
  final String? uuidProduk;
  final String namaProduk;
  final String? sku;
  final String simbolSatuan;
  final bool bolehDesimal;
  final String pelacakan;
  final String? nomorBatch;
  final String? tanggalKedaluwarsa;
  final String? nomorSeri;
  final String? jumlahSistem;
  final String? jumlahFisik;

  static BarisOpnameGudang DariJson(Map<String, Object?> json) => BarisOpnameGudang(
    urutan: UraiJson.AmbilBulat(json['Urutan']),
    uuidProduk: UraiJson.AmbilTeksAtauNull(json['UuidProduk']),
    namaProduk: UraiJson.AmbilTeks(json['NamaProduk']),
    sku: UraiJson.AmbilTeksAtauNull(json['Sku']),
    simbolSatuan: UraiJson.AmbilTeks(json['SimbolSatuan']),
    bolehDesimal: UraiJson.AmbilBenar(json['BolehDesimal']),
    pelacakan: UraiJson.AmbilTeks(json['Pelacakan'], 'Tidak'),
    nomorBatch: UraiJson.AmbilTeksAtauNull(json['NomorBatch']),
    tanggalKedaluwarsa: UraiJson.AmbilTeksAtauNull(json['TanggalKedaluwarsa']),
    nomorSeri: UraiJson.AmbilTeksAtauNull(json['NomorSeri']),
    jumlahSistem: UraiJson.AmbilDesimalAtauNull(json['JumlahSistem']),
    jumlahFisik: UraiJson.AmbilDesimalAtauNull(json['JumlahFisik']),
  );
}

/// Stok opname yang sedang berlangsung di lokasi outlet perangkat.
class OpnameGudangPos {
  const OpnameGudangPos({
    required this.uuid,
    required this.nomor,
    required this.namaLokasi,
    required this.namaKategori,
    required this.hitungButa,
    required this.jumlahBaris,
    required this.jumlahDihitung,
    required this.baris,
  });

  final String uuid;
  final String nomor;
  final String namaLokasi;
  final String? namaKategori;
  final bool hitungButa;
  final int jumlahBaris;
  final int jumlahDihitung;
  final List<BarisOpnameGudang> baris;

  static OpnameGudangPos DariJson(Map<String, Object?> json) => OpnameGudangPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomor: UraiJson.AmbilTeks(json['Nomor']),
    namaLokasi: UraiJson.AmbilTeks(json['NamaLokasi']),
    namaKategori: UraiJson.AmbilTeksAtauNull(json['NamaKategori']),
    hitungButa: UraiJson.AmbilBenar(json['HitungButa']),
    jumlahBaris: UraiJson.AmbilBulat(json['JumlahBaris']),
    jumlahDihitung: UraiJson.AmbilBulat(json['JumlahDihitung']),
    baris: [for (final b in UraiJson.AmbilDaftarPeta(json['Baris'])) BarisOpnameGudang.DariJson(b)],
  );
}
