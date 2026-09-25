import 'package:mesin_kasir/MesinKasir.dart';

import '../Katalog/KatalogLokal.dart';
import '../Meja/KonteksPesananMeja.dart';

/// Diskon manual baris atau pesanan: tepat satu dari [persen] atau [jumlah] (BR-07.3).
class DiskonManual {
  const DiskonManual._({this.persen, this.jumlah});

  static DiskonManual DariPersen(Decimal persen) => DiskonManual._(persen: persen);

  static DiskonManual DariJumlah(Uang jumlah) => DiskonManual._(jumlah: jumlah);

  final Decimal? persen;
  final Uang? jumlah;

  DataPotongan KePotongan() => persen != null ? DataPotongan.DariPersen(persen!) : DataPotongan.DariJumlah(jumlah!);

  /// Bentuk kontrak `DiskonManual {Persen|Jumlah}` (Rincian F-07b).
  Map<String, Object?> KeJson() => persen != null ? {'Persen': persen.toString()} : {'Jumlah': jumlah!.KeString()};

  static DiskonManual? DariJson(Object? json) {
    if (json is! Map<String, Object?>) {
      return null;
    }
    if (json['Persen'] is String) {
      return DariPersen(Decimal.parse(json['Persen']! as String));
    }
    return json['Jumlah'] is String ? DariJumlah(Uang.Dari(json['Jumlah']! as String)) : null;
  }

  String AmbilLabel() => persen != null ? 'Diskon ${persen.toString().replaceAll('.', ',')}%' : 'Diskon';
}

class PilihanTerpilih {
  const PilihanTerpilih({required this.uuid, required this.nama, required this.harga});

  final String uuid;
  final String nama;
  final Uang harga;

  /// Bentuk kontrak `Pilihan [{UuidPilihan, Nama, Harga}]`.
  Map<String, Object?> KeJson() => {'UuidPilihan': uuid, 'Nama': nama, 'Harga': harga.KeString()};

  static PilihanTerpilih DariJson(Map<String, Object?> json) => PilihanTerpilih(
    uuid: json['UuidPilihan']! as String,
    nama: json['Nama']! as String,
    harga: Uang.Dari(json['Harga']! as String),
  );
}

/// Satu baris keranjang dengan snapshot harga & pajak saat ditambahkan (BR-07.2).
class ItemKeranjang {
  const ItemKeranjang({
    required this.uuid,
    required this.uuidProduk,
    required this.nama,
    required this.uuidProdukSatuan,
    required this.namaSatuan,
    required this.bolehDesimal,
    required this.jumlah,
    required this.hargaSatuan,
    this.pilihan = const [],
    this.catatan,
    this.diskon,
    this.hargaTermasukPajak,
    this.pajak = const [],
    this.staf = const [],
  });

  final String uuid;
  final String uuidProduk;
  final String nama;
  final String? uuidProdukSatuan;
  final String? namaSatuan;
  final bool bolehDesimal;
  final Kuantitas jumlah;
  final Uang hargaSatuan;
  final List<PilihanTerpilih> pilihan;
  final String? catatan;
  final DiskonManual? diskon;

  /// Null = ikut pengaturan outlet.
  final bool? hargaTermasukPajak;

  /// Jenis pajak dari kelompok pajak produk (disaring profil & tarif saat dihitung).
  final List<PajakProduk> pajak;

  /// F-18: Uuid karyawan yang melayani baris ini (komisi dibagi rata di server).
  final List<String> staf;

  Uang AmbilHargaPilihan() => pilihan.fold(Uang.Nol(), (total, p) => total.Tambah(p.harga));

  /// Baris yang sama (produk, satuan, pilihan) tanpa catatan & diskon digabung saat produk ditambah lagi.
  bool CekBisaDigabung(ItemKeranjang lain) =>
      uuidProduk == lain.uuidProduk &&
      uuidProdukSatuan == lain.uuidProdukSatuan &&
      catatan == null &&
      lain.catatan == null &&
      diskon == null &&
      lain.diskon == null &&
      staf.isEmpty &&
      lain.staf.isEmpty &&
      pilihan.map((p) => p.uuid).toSet().containsAll(lain.pilihan.map((p) => p.uuid)) &&
      pilihan.length == lain.pilihan.length;

  ItemKeranjang Salin({
    Kuantitas? jumlah,
    Uang? hargaSatuan,
    String? uuidProdukSatuan,
    String? namaSatuan,
    bool? bolehDesimal,
    List<PilihanTerpilih>? pilihan,
    String? Function()? catatan,
    DiskonManual? Function()? diskon,
    List<String>? staf,
  }) => ItemKeranjang(
    uuid: uuid,
    uuidProduk: uuidProduk,
    nama: nama,
    uuidProdukSatuan: uuidProdukSatuan ?? this.uuidProdukSatuan,
    namaSatuan: namaSatuan ?? this.namaSatuan,
    bolehDesimal: bolehDesimal ?? this.bolehDesimal,
    jumlah: jumlah ?? this.jumlah,
    hargaSatuan: hargaSatuan ?? this.hargaSatuan,
    pilihan: pilihan ?? this.pilihan,
    catatan: catatan == null ? this.catatan : catatan(),
    diskon: diskon == null ? this.diskon : diskon(),
    hargaTermasukPajak: hargaTermasukPajak,
    pajak: pajak,
    staf: staf ?? this.staf,
  );

  Map<String, Object?> KeJson() => {
    'Uuid': uuid,
    'UuidProduk': uuidProduk,
    'Nama': nama,
    'UuidProdukSatuan': uuidProdukSatuan,
    'NamaSatuan': namaSatuan,
    'BolehDesimal': bolehDesimal,
    'Jumlah': jumlah.KeString(),
    'HargaSatuan': hargaSatuan.KeString(),
    'Pilihan': [for (final p in pilihan) p.KeJson()],
    'Catatan': catatan,
    'DiskonManual': diskon?.KeJson(),
    'HargaTermasukPajak': hargaTermasukPajak,
    'Pajak': [for (final p in pajak) p.KeJson()],
    'Staf': staf,
  };

  static ItemKeranjang DariJson(Map<String, Object?> json) => ItemKeranjang(
    uuid: json['Uuid']! as String,
    uuidProduk: json['UuidProduk']! as String,
    nama: json['Nama']! as String,
    uuidProdukSatuan: json['UuidProdukSatuan'] as String?,
    namaSatuan: json['NamaSatuan'] as String?,
    bolehDesimal: json['BolehDesimal'] == true,
    jumlah: Kuantitas.Dari(json['Jumlah']! as String),
    hargaSatuan: Uang.Dari(json['HargaSatuan']! as String),
    pilihan: [
      for (final p in (json['Pilihan'] as List<Object?>? ?? const []).whereType<Map<String, Object?>>())
        PilihanTerpilih.DariJson(p),
    ],
    catatan: json['Catatan'] as String?,
    diskon: DiskonManual.DariJson(json['DiskonManual']),
    hargaTermasukPajak: json['HargaTermasukPajak'] as bool?,
    pajak: [
      for (final p in (json['Pajak'] as List<Object?>? ?? const []).whereType<Map<String, Object?>>())
        PajakProduk.DariJson(p),
    ],
    staf: [...(json['Staf'] as List<Object?>? ?? const []).whereType<String>()],
  );
}

/// Staf yang menyetujui diskon di atas batas (BR-07.3), lolos PIN.
class PenyetujuDiskon {
  const PenyetujuDiskon({required this.uuid, required this.nama, required this.pemilik});

  final String uuid;
  final String nama;
  final bool pemilik;

  Map<String, Object?> KeJson() => {'Uuid': uuid, 'Nama': nama, 'Pemilik': pemilik};

  static PenyetujuDiskon? DariJson(Object? json) => json is Map<String, Object?> && json['Uuid'] is String
      ? PenyetujuDiskon(uuid: json['Uuid']! as String, nama: '${json['Nama'] ?? ''}', pemilik: json['Pemilik'] == true)
      : null;
}

/// Pelanggan yang dipilih untuk transaksi (F-16a). Nomor HP hanya tersamar. F-16b: [kodeTier] menentukan harga per
/// tier; [saldoPoin] hanya informasi dari pencarian online (null = tidak diketahui/offline). F-12: posisi kredit
/// terakhir yang diketahui perangkat untuk cek BR-12.1 ([sisaPiutang] null = belum pernah diketahui).
class PelangganTerpilih {
  const PelangganTerpilih({
    required this.uuid,
    required this.nama,
    required this.noHpSamar,
    this.kodeTier,
    this.namaTier,
    this.saldoPoin,
    this.limitKredit,
    this.sisaPiutang,
    this.hariLewatJatuhTempo,
  });

  final String uuid;
  final String nama;
  final String noHpSamar;
  final String? kodeTier;
  final String? namaTier;
  final int? saldoPoin;
  final String? limitKredit;
  final String? sisaPiutang;
  final int? hariLewatJatuhTempo;

  Map<String, Object?> KeJson() => {
    'Uuid': uuid,
    'Nama': nama,
    'NoHpSamar': noHpSamar,
    'KodeTier': kodeTier,
    'NamaTier': namaTier,
    'SaldoPoin': saldoPoin,
    'LimitKredit': limitKredit,
    'SisaPiutang': sisaPiutang,
    'HariLewatJatuhTempo': hariLewatJatuhTempo,
  };

  static PelangganTerpilih? DariJson(Object? json) => json is Map<String, Object?> && json['Uuid'] is String
      ? PelangganTerpilih(
          uuid: json['Uuid']! as String,
          nama: '${json['Nama'] ?? ''}',
          noHpSamar: '${json['NoHpSamar'] ?? ''}',
          kodeTier: json['KodeTier'] as String?,
          namaTier: json['NamaTier'] as String?,
          saldoPoin: json['SaldoPoin'] as int?,
          limitKredit: json['LimitKredit'] as String?,
          sisaPiutang: json['SisaPiutang'] as String?,
          hariLewatJatuhTempo: json['HariLewatJatuhTempo'] as int?,
        )
      : null;
}

/// Poin pelanggan yang ditukar sebagai diskon pesanan sebelum pajak (F-16b, J-16.4). Diperiksa online saat dipasang
/// (§18.4); [nilai] = poin × nilai tukar per poin, dibatasi sisa subtotal.
class TukarPoin {
  const TukarPoin({required this.poin, required this.nilai});

  final int poin;
  final Uang nilai;

  Map<String, Object?> KeJson() => {'Poin': poin, 'Nilai': nilai.KeString()};

  static TukarPoin? DariJson(Object? json) =>
      json is Map<String, Object?> && json['Poin'] is int && json['Nilai'] is String
      ? TukarPoin(poin: json['Poin']! as int, nilai: Uang.Dari(json['Nilai']! as String))
      : null;
}

/// Voucher yang dipesan online untuk keranjang ini (F-16c bagian 2, wajib online). [uuidPenjualan] = Uuid penjualan yang
/// akan dibuat (voucher dipesan server untuk penjualan itu); [promo] = promo voucher bentuk `PromoPos.KeJson` agar
/// bisa dievaluasi walau daftar promo tersimpan belum diperbarui.
class VoucherKeranjang {
  const VoucherKeranjang({
    required this.kode,
    required this.uuidPenjualan,
    required this.uuidPromo,
    required this.namaPromo,
    required this.promo,
  });

  final String kode;
  final String uuidPenjualan;
  final String uuidPromo;
  final String namaPromo;
  final Map<String, Object?> promo;

  Map<String, Object?> KeJson() => {
    'Kode': kode,
    'UuidPenjualan': uuidPenjualan,
    'UuidPromo': uuidPromo,
    'NamaPromo': namaPromo,
    'Promo': promo,
  };

  static VoucherKeranjang? DariJson(Object? json) =>
      json is Map<String, Object?> && json['Kode'] is String && json['UuidPenjualan'] is String
      ? VoucherKeranjang(
          kode: json['Kode']! as String,
          uuidPenjualan: json['UuidPenjualan']! as String,
          uuidPromo: '${json['UuidPromo'] ?? ''}',
          namaPromo: '${json['NamaPromo'] ?? ''}',
          promo: json['Promo'] is Map<String, Object?> ? json['Promo']! as Map<String, Object?> : const {},
        )
      : null;
}

/// Keranjang yang sedang dibangun kasir (belum tersimpan sebagai penjualan). [pesananMeja] terisi saat pesanan meja
/// dibuka (F-07 mode meja): pembayarannya menutup pesanan terbuka itu.
class Keranjang {
  const Keranjang({
    this.baris = const [],
    this.diskonPesanan,
    this.penyetuju,
    this.catatan,
    this.pesananMeja,
    this.pelanggan,
    this.tukarPoin,
    this.voucher,
  });

  static const Keranjang kosong = Keranjang();

  final List<ItemKeranjang> baris;
  final DiskonManual? diskonPesanan;
  final PenyetujuDiskon? penyetuju;
  final String? catatan;
  final KonteksPesananMeja? pesananMeja;

  /// F-16a: null = pelanggan umum.
  final PelangganTerpilih? pelanggan;

  /// F-16b: poin [pelanggan] yang ditukar; dilepas saat pelanggan diganti.
  final TukarPoin? tukarPoin;

  /// F-16c bagian 2: voucher yang sudah dipesan online.
  final VoucherKeranjang? voucher;

  bool get CekKosong => baris.isEmpty;

  Kuantitas HitungJumlahItem() => baris.fold(Kuantitas.Nol(), (total, b) => total.Tambah(b.jumlah));

  Keranjang Salin({
    List<ItemKeranjang>? baris,
    DiskonManual? Function()? diskonPesanan,
    PenyetujuDiskon? Function()? penyetuju,
    String? Function()? catatan,
    KonteksPesananMeja? Function()? pesananMeja,
    PelangganTerpilih? Function()? pelanggan,
    TukarPoin? Function()? tukarPoin,
    VoucherKeranjang? Function()? voucher,
  }) => Keranjang(
    baris: baris ?? this.baris,
    diskonPesanan: diskonPesanan == null ? this.diskonPesanan : diskonPesanan(),
    penyetuju: penyetuju == null ? this.penyetuju : penyetuju(),
    catatan: catatan == null ? this.catatan : catatan(),
    pesananMeja: pesananMeja == null ? this.pesananMeja : pesananMeja(),
    pelanggan: pelanggan == null ? this.pelanggan : pelanggan(),
    tukarPoin: tukarPoin == null ? this.tukarPoin : tukarPoin(),
    voucher: voucher == null ? this.voucher : voucher(),
  );

  Map<String, Object?> KeJson() => {
    'Baris': [for (final b in baris) b.KeJson()],
    'DiskonManualPesanan': diskonPesanan?.KeJson(),
    'Penyetuju': penyetuju?.KeJson(),
    'Catatan': catatan,
    'Pelanggan': pelanggan?.KeJson(),
    'TukarPoin': tukarPoin?.KeJson(),
    'Voucher': voucher?.KeJson(),
  };

  static Keranjang DariJson(Map<String, Object?> json) => Keranjang(
    baris: [
      for (final b in (json['Baris'] as List<Object?>? ?? const []).whereType<Map<String, Object?>>())
        ItemKeranjang.DariJson(b),
    ],
    diskonPesanan: DiskonManual.DariJson(json['DiskonManualPesanan']),
    penyetuju: PenyetujuDiskon.DariJson(json['Penyetuju']),
    catatan: json['Catatan'] as String?,
    pelanggan: PelangganTerpilih.DariJson(json['Pelanggan']),
    tukarPoin: TukarPoin.DariJson(json['TukarPoin']),
    voucher: VoucherKeranjang.DariJson(json['Voucher']),
  );
}
