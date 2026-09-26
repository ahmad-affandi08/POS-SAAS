import 'package:inti/Inti.dart';

import '../Harga/DataHarga.dart';
import '../Kalkulasi/DataKalkulasi.dart';
import '../Kalkulasi/HasilKalkulasi.dart';

/// Barang yang memicu promo (F-16c): semua barang, produk tertentu, atau kategori tertentu.
enum JenisKondisiPromo { Semua, Produk, Kategori }

/// Aksi promo F-16c bagian 1. `*Item`, `HargaSpesial`, `BeliXGratisY`, dan `BundelHargaTetap` memotong baris barang
/// yang memenuhi kondisi; `*Pesanan` memotong pesanan (sebelum pajak).
enum JenisAksiPromo {
  DiskonPersenItem,
  DiskonTetapItem,
  HargaSpesial,
  DiskonPersenPesanan,
  DiskonTetapPesanan,
  BeliXGratisY,
  BundelHargaTetap,
}

/// Cara memilih promo yang berlaku bersamaan: `Terbaik` = kombinasi dengan potongan terbesar untuk pelanggan (semua
/// promo non-eksklusif bersama, atau satu promo eksklusif); `PrioritasKetat` = urut prioritas, promo eksklusif
/// menghentikan evaluasi.
enum ModeResolusiPromo { Terbaik, PrioritasKetat }

/// Jendela promo ulang tahun (F-16c bagian 3) terhadap tanggal lokal outlet: `Hari` = tepat hari ulang tahun,
/// `Rentang` = ulang tahun ± N hari, `Bulan` = sepanjang bulan lahir. 29 Februari = 28 Februari di tahun bukan kabisat.
enum JenisUlangTahunPromo { Hari, Rentang, Bulan }

/// Periode batas pemakaian promo per pelanggan (F-16c bagian 3): per tanggal bisnis, atau selama masa promo.
enum PeriodeBatasPelangganPromo { Hari, Promo }

/// Pemakaian satu promo oleh pelanggan sebelum transaksi ini: hari ini dan selama masa promo.
final class PemakaianPromoPelanggan {
  const PemakaianPromoPelanggan({this.hari = 0, this.promo = 0});

  final int hari;
  final int promo;

  int Ambil(PeriodeBatasPelangganPromo periode) => periode == PeriodeBatasPelangganPromo.Hari ? hari : promo;
}

/// Definisi satu promo (PRD F-16 Promo Engine, "Rincian F-16c"). Daftar kosong = tanpa batasan. Waktu
/// `[mulaiPada, selesaiPada)` UTC; [hari] 1 = Senin … 7 = Minggu dan jam `[jamMulai, jamSelesai)` (menit sejak 00:00)
/// memakai jam lokal outlet; jam selesai ≤ jam mulai = melewati tengah malam.
final class DefinisiPromo {
  DefinisiPromo({
    required this.uuid,
    required this.kode,
    required this.aksi,
    this.prioritas = 0,
    this.eksklusif = false,
    this.mulaiPada,
    this.selesaiPada,
    this.hari = const [],
    this.jamMulai,
    this.jamSelesai,
    this.uuidOutlet = const [],
    this.kanal = const [],
    this.tier = const [],
    Uang? minimalSubtotal,
    this.kondisi = JenisKondisiPromo.Semua,
    this.uuidKondisi = const [],
    Kuantitas? jumlahMinimal,
    this.persen,
    this.jumlah,
    this.harga,
    this.beli,
    this.gratis,
    Decimal? persenGratis,
    this.batasPerTransaksi,
    this.kuotaTersisa,
    this.wajibVoucher = false,
    this.metodeBayar = const [],
    this.ulangTahun,
    this.hariUlangTahun = 0,
    this.transaksiPertama = false,
    this.batasPerPelanggan,
    this.periodeBatasPelanggan = PeriodeBatasPelangganPromo.Hari,
  }) : minimalSubtotal = minimalSubtotal ?? Uang.Nol(),
       jumlahMinimal = jumlahMinimal ?? Kuantitas.Nol(),
       persenGratis = persenGratis ?? Decimal.fromInt(100);

  final String uuid;
  final String kode;
  final JenisAksiPromo aksi;

  /// Lebih besar dievaluasi lebih dulu; seri diurutkan menurut [kode].
  final int prioritas;
  final bool eksklusif;
  final DateTime? mulaiPada;
  final DateTime? selesaiPada;
  final List<int> hari;
  final int? jamMulai;
  final int? jamSelesai;
  final List<String> uuidOutlet;
  final List<KanalPenjualan> kanal;

  /// Kode tier pelanggan yang berhak; kosong = semua pembeli (termasuk tanpa pelanggan).
  final List<String> tier;

  /// Subtotal minimal sebelum promo (setelah diskon manual baris).
  final Uang minimalSubtotal;
  final JenisKondisiPromo kondisi;
  final List<String> uuidKondisi;

  /// Jumlah minimal barang yang memenuhi kondisi (untuk `BundelHargaTetap` = isi satu bundel).
  final Kuantitas jumlahMinimal;

  /// `DiskonPersen*`: persen potongan.
  final Decimal? persen;

  /// `DiskonTetapItem`: potongan per satuan; `DiskonTetapPesanan`: potongan pesanan.
  final Uang? jumlah;

  /// `HargaSpesial`: harga per satuan; `BundelHargaTetap`: harga satu bundel.
  final Uang? harga;

  /// `BeliXGratisY`: beli [beli] satuan, [gratis] satuan berikutnya (termurah dalam satu set) dipotong [persenGratis].
  final int? beli;
  final int? gratis;
  final Decimal persenGratis;

  /// Set `BeliXGratisY`/`BundelHargaTetap` terbanyak per transaksi; null = tanpa batas.
  final int? batasPerTransaksi;

  /// Sisa kuota pemakaian; null = tanpa kuota, 0 = habis.
  final int? kuotaTersisa;

  /// F-16c bagian 2: promo hanya berlaku bila kode vouchernya sudah divalidasi server untuk transaksi ini.
  final bool wajibVoucher;

  /// F-16c bagian 3: Uuid metode pembayaran; semua pembayaran transaksi wajib memakai salah satunya.
  final List<String> metodeBayar;

  /// F-16c bagian 3: promo ulang tahun pelanggan; [hariUlangTahun] = jarak ± hari untuk `Rentang`.
  final JenisUlangTahunPromo? ulangTahun;
  final int hariUlangTahun;

  /// F-16c bagian 3: hanya untuk pelanggan yang belum pernah bertransaksi.
  final bool transaksiPertama;

  /// F-16c bagian 3: pemakaian maksimal per pelanggan per [periodeBatasPelanggan]; null = tanpa batas.
  final int? batasPerPelanggan;
  final PeriodeBatasPelangganPromo periodeBatasPelanggan;

  /// Membaca kolom promo + `Definisi` JSON (bentuk yang sama di tabel `Promo`, katalog POS, dan test vector):
  /// `{Hari, JamMulai "HH:MM", JamSelesai, Outlet, Kanal, Tier, MinimalSubtotal, Kondisi {Jenis, Uuid, JumlahMinimal},
  /// Aksi {Jenis, Persen, Jumlah, Harga, Beli, Gratis, PersenGratis}, BatasPerTransaksi, WajibVoucher, MetodeBayar [],
  /// UlangTahun {Jenis, Hari}, TransaksiPertama, BatasPerPelanggan {Jumlah, Periode}}`.
  static DefinisiPromo Urai({
    required String uuid,
    required String kode,
    required Map<String, Object?> definisi,
    int prioritas = 0,
    bool eksklusif = false,
    DateTime? mulaiPada,
    DateTime? selesaiPada,
    int? kuotaTersisa,
  }) {
    final kondisi = (definisi['Kondisi'] as Map<String, Object?>?) ?? const {};
    final aksi = definisi['Aksi']! as Map<String, Object?>;
    final ulangTahun = (definisi['UlangTahun'] as Map<String, Object?>?) ?? const {};
    final batas = (definisi['BatasPerPelanggan'] as Map<String, Object?>?) ?? const {};
    String? Teks(Object? nilai) => nilai is String && nilai.isNotEmpty ? nilai : null;
    List<String> Daftar(Object? nilai) => nilai is List ? nilai.whereType<String>().toList() : const [];
    int? Menit(Object? nilai) {
      final teks = Teks(nilai);
      if (teks == null) {
        return null;
      }
      final bagian = teks.split(':');
      return int.parse(bagian[0]) * 60 + int.parse(bagian[1]);
    }

    return DefinisiPromo(
      uuid: uuid,
      kode: kode,
      prioritas: prioritas,
      eksklusif: eksklusif,
      mulaiPada: mulaiPada,
      selesaiPada: selesaiPada,
      kuotaTersisa: kuotaTersisa,
      aksi: JenisAksiPromo.values.byName(aksi['Jenis']! as String),
      hari: definisi['Hari'] is List ? (definisi['Hari']! as List).whereType<int>().toList() : const [],
      jamMulai: Menit(definisi['JamMulai']),
      jamSelesai: Menit(definisi['JamSelesai']),
      uuidOutlet: Daftar(definisi['Outlet']),
      kanal: [for (final k in Daftar(definisi['Kanal'])) KanalPenjualan.values.byName(k)],
      tier: Daftar(definisi['Tier']),
      minimalSubtotal: Uang.Dari(Teks(definisi['MinimalSubtotal']) ?? '0'),
      kondisi: JenisKondisiPromo.values.byName(Teks(kondisi['Jenis']) ?? 'Semua'),
      uuidKondisi: Daftar(kondisi['Uuid']),
      jumlahMinimal: Kuantitas.Dari(Teks(kondisi['JumlahMinimal']) ?? '0'),
      persen: Teks(aksi['Persen']) == null ? null : Decimal.parse(aksi['Persen']! as String),
      jumlah: Teks(aksi['Jumlah']) == null ? null : Uang.Dari(aksi['Jumlah']! as String),
      harga: Teks(aksi['Harga']) == null ? null : Uang.Dari(aksi['Harga']! as String),
      beli: aksi['Beli'] as int?,
      gratis: aksi['Gratis'] as int?,
      persenGratis: Teks(aksi['PersenGratis']) == null ? null : Decimal.parse(aksi['PersenGratis']! as String),
      batasPerTransaksi: definisi['BatasPerTransaksi'] as int?,
      wajibVoucher: definisi['WajibVoucher'] == true,
      metodeBayar: Daftar(definisi['MetodeBayar']),
      ulangTahun: Teks(ulangTahun['Jenis']) == null
          ? null
          : JenisUlangTahunPromo.values.byName(ulangTahun['Jenis']! as String),
      hariUlangTahun: ulangTahun['Hari'] is int && (ulangTahun['Hari']! as int) > 0 ? ulangTahun['Hari']! as int : 0,
      transaksiPertama: definisi['TransaksiPertama'] == true,
      batasPerPelanggan: batas['Jumlah'] as int?,
      periodeBatasPelanggan: Teks(batas['Periode']) == null
          ? PeriodeBatasPelangganPromo.Hari
          : PeriodeBatasPelangganPromo.values.byName(batas['Periode']! as String),
    );
  }
}

/// Data barang per baris keranjang untuk kondisi promo; urutan sama dengan `DataKalkulasi.baris`.
final class BarisPromo {
  const BarisPromo({required this.uuidProduk, this.uuidKategori});

  final String uuidProduk;
  final String? uuidKategori;
}

/// Konteks transaksi: waktu UTC, jam dinding lokal outlet (tanpa zona), outlet, kanal, tier pelanggan, dan (F-16c
/// bagian 2) Uuid promo yang vouchernya sudah divalidasi untuk transaksi ini. Bagian 3: Uuid metode semua pembayaran
/// (null = belum memilih pembayaran), ada tidaknya pelanggan, tanggal lahirnya `YYYY-MM-DD`, jumlah transaksinya
/// sebelum ini (null = tidak diketahui), dan pemakaian promo oleh pelanggan itu per Uuid promo.
final class KonteksPromo {
  const KonteksPromo({
    required this.waktu,
    required this.waktuLokal,
    this.uuidOutlet,
    this.kanal,
    this.tier,
    this.voucher = const [],
    this.metodeBayar,
    this.berpelanggan = false,
    this.tanggalLahir,
    this.jumlahTransaksiPelanggan,
    this.pemakaianPelanggan = const {},
  });

  final DateTime waktu;
  final DateTime waktuLokal;
  final String? uuidOutlet;
  final KanalPenjualan? kanal;
  final String? tier;
  final List<String> voucher;
  final List<String>? metodeBayar;
  final bool berpelanggan;
  final String? tanggalLahir;
  final int? jumlahTransaksiPelanggan;
  final Map<String, PemakaianPromoPelanggan> pemakaianPelanggan;
}

/// Promo yang diterapkan: potongan per indeks baris dan potongan pesanan.
final class PromoTerpakai {
  const PromoTerpakai({required this.uuid, required this.kode, required this.diskonBaris, required this.diskonPesanan});

  final String uuid;
  final String kode;
  final Map<int, Uang> diskonBaris;
  final Uang diskonPesanan;

  Uang HitungTotal() => diskonBaris.values.fold(diskonPesanan, (a, b) => a.Tambah(b));
}

/// Hasil `MesinPromo`: promo terpakai (urut evaluasi), masukan kalkulasi yang sudah berisi potongan promo, dan hasil
/// mesin kalkulasi atas masukan itu.
final class HasilPromo {
  const HasilPromo({required this.terpakai, required this.data, required this.hasil});

  final List<PromoTerpakai> terpakai;
  final DataKalkulasi data;
  final HasilKalkulasi hasil;
}
