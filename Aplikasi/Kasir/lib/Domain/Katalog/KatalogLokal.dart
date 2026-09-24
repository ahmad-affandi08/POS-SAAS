import 'dart:convert';

import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart' hide BarisProdukHarga;
import '../../Data/RepositoriKatalog.dart';

/// Jenis & pelacakan produk yang belum bisa dijual di POS fase 1 (Rincian F-07b langkah 5).
abstract final class JenisProdukKasir {
  static const String indukVarian = 'IndukVarian';
  static const String bahanBaku = 'BahanBaku';
  static const String konsinyasi = 'Konsinyasi';
  static const String pelacakanTidak = 'Tidak';
}

/// Satuan jual produk (baris `ProdukSatuan` + nama satuan).
class SatuanJual {
  const SatuanJual({
    required this.uuid,
    required this.nama,
    required this.konversiKeDasar,
    required this.defaultJual,
    required this.bolehDesimal,
  });

  /// Uuid `ProdukSatuan`.
  final String uuid;
  final String nama;
  final String konversiKeDasar;
  final bool defaultJual;
  final bool bolehDesimal;
}

class PilihanJual {
  const PilihanJual({required this.uuid, required this.nama, required this.harga});

  final String uuid;
  final String nama;
  final Uang harga;
}

/// Kelompok pilihan (modifier) produk. `minimal ≥ 1` = wajib; `maksimal` null = tanpa batas atas.
class KelompokPilihanJual {
  const KelompokPilihanJual({
    required this.uuid,
    required this.nama,
    required this.minimal,
    required this.maksimal,
    required this.pilihan,
  });

  final String uuid;
  final String nama;
  final int minimal;
  final int? maksimal;
  final List<PilihanJual> pilihan;

  bool CekWajib() => minimal > 0;

  bool CekSatuSaja() => maksimal == 1;
}

/// Jenis pajak kelompok pajak produk: kode jenis (`Ppn`, `PbjtMakananMinuman`, ...) dan dasar pengenaan.
class PajakProduk {
  const PajakProduk({required this.kode, required this.dasarPengenaan});

  final String kode;
  final String dasarPengenaan;

  Map<String, Object?> KeJson() => {'Kode': kode, 'DasarPengenaan': dasarPengenaan};

  static PajakProduk DariJson(Map<String, Object?> json) => PajakProduk(
    kode: json['Kode'] is String ? json['Kode']! as String : '',
    dasarPengenaan: json['DasarPengenaan'] is String ? json['DasarPengenaan']! as String : 'Subtotal',
  );
}

class ProdukJual {
  const ProdukJual({
    required this.uuid,
    required this.sku,
    required this.nama,
    required this.jenis,
    required this.uuidKategori,
    required this.pelacakan,
    required this.hargaTermasukPajak,
    required this.tampil,
    required this.satuan,
    required this.kelompokPilihan,
    required this.pajak,
  });

  final String uuid;
  final String? sku;
  final String nama;
  final String jenis;
  final String? uuidKategori;
  final String pelacakan;

  /// Null = ikut pengaturan pajak outlet.
  final bool? hargaTermasukPajak;

  /// Aktif & tampil di POS.
  final bool tampil;
  final List<SatuanJual> satuan;
  final List<KelompokPilihanJual> kelompokPilihan;
  final List<PajakProduk> pajak;

  SatuanJual? AmbilSatuanBawaan() {
    for (final s in satuan) {
      if (s.defaultJual) {
        return s;
      }
    }
    return satuan.isEmpty ? null : satuan.first;
  }

  /// Alasan produk tidak bisa dijual di POS fase 1 (null = bisa dijual) beserta kode galat server padanannya.
  ({String kode, String pesan})? AmbilAlasanTidakBisaDijual() {
    if (pelacakan != JenisProdukKasir.pelacakanTidak) {
      return (
        kode: 'PelacakanBelumDidukung',
        pesan: '"$nama" memakai pelacakan ${pelacakan.toLowerCase()} dan belum bisa dijual di aplikasi kasir.',
      );
    }
    return switch (jenis) {
      JenisProdukKasir.indukVarian => (
        kode: 'ProdukTidakBisaDijual',
        pesan: '"$nama" adalah induk varian. Pilih salah satu variannya.',
      ),
      JenisProdukKasir.bahanBaku => (
        kode: 'ProdukTidakBisaDijual',
        pesan: '"$nama" adalah bahan baku dan tidak dijual ke pelanggan.',
      ),
      JenisProdukKasir.konsinyasi => (
        kode: 'ProdukTidakBisaDijual',
        pesan: 'Produk konsinyasi "$nama" belum bisa dijual di aplikasi kasir.',
      ),
      _ => null,
    };
  }
}

/// Hasil cari kode (barcode/SKU): produk beserta satuan barcode bila barcode terikat satuan tertentu.
typedef HasilCariKode = ({ProdukJual produk, SatuanJual? satuan});

/// Katalog lokal di memori untuk layar Jual: produk siap jual, kategori, barcode, kelompok pajak, dan data harga
/// untuk `PenentuHarga`. Dibangun ulang setiap katalog diperbarui.
class KatalogLokal {
  KatalogLokal._({
    required this.produk,
    required this.kategori,
    required this._petaProduk,
    required this._barcode,
    required this.daftarHarga,
    required this._hargaPerProduk,
  });

  static final KatalogLokal kosong = KatalogLokal._(
    produk: const [],
    kategori: const [],
    petaProduk: const {},
    barcode: const {},
    daftarHarga: const [],
    hargaPerProduk: const {},
  );

  /// Semua produk (urut nama), termasuk yang tidak tampil (untuk pesanan tertahan & barcode).
  final List<ProdukJual> produk;
  final List<BarisKategori> kategori;
  final List<DaftarHargaResolusi> daftarHarga;
  final Map<String, ProdukJual> _petaProduk;
  final Map<String, ({String uuidProduk, String? uuidProdukSatuan})> _barcode;
  final Map<String, List<BarisProdukHarga>> _hargaPerProduk;

  bool get CekKosong => produk.isEmpty;

  ProdukJual? CariProduk(String uuid) => _petaProduk[uuid];

  /// Data harga satu produk untuk `PenentuHarga` (baris harga produk itu saja, agar cepat untuk katalog besar).
  KatalogHarga AmbilKatalogHarga(String uuidProduk) =>
      KatalogHarga(daftarHarga: daftarHarga, harga: _hargaPerProduk[uuidProduk] ?? const []);

  /// Produk tampil di grid: aktif & tampil di POS, disaring kategori (termasuk subkategori) dan kata kunci
  /// (nama/SKU/barcode).
  List<ProdukJual> AmbilTampil({String? uuidKategori, String kata = ''}) {
    final kunci = kata.trim().toLowerCase();
    final subKategori = uuidKategori == null
        ? const <String>{}
        : {uuidKategori, ...kategori.where((k) => k.UuidInduk == uuidKategori).map((k) => k.Uuid)};
    final uuidBarcode = kunci.isEmpty ? null : _barcode[kata.trim()]?.uuidProduk;
    return produk.where((p) {
      if (!p.tampil) {
        return false;
      }
      if (uuidKategori != null && !subKategori.contains(p.uuidKategori)) {
        return false;
      }
      if (kunci.isEmpty) {
        return true;
      }
      return p.nama.toLowerCase().contains(kunci) ||
          (p.sku?.toLowerCase().contains(kunci) ?? false) ||
          p.uuid == uuidBarcode;
    }).toList();
  }

  /// Cari persis berdasarkan barcode lalu SKU (tanpa beda huruf besar/kecil). Dipakai pemindai & Enter di kolom cari.
  HasilCariKode? CariKode(String kode) {
    final rapi = kode.trim();
    if (rapi.isEmpty) {
      return null;
    }
    final kodeBarcode = _barcode[rapi];
    if (kodeBarcode != null) {
      final p = _petaProduk[kodeBarcode.uuidProduk];
      if (p != null) {
        final satuan = p.satuan.where((s) => s.uuid == kodeBarcode.uuidProdukSatuan).firstOrNull;
        return (produk: p, satuan: satuan);
      }
    }
    final kecil = rapi.toLowerCase();
    for (final p in produk) {
      if (p.sku != null && p.sku!.toLowerCase() == kecil) {
        return (produk: p, satuan: null);
      }
    }
    return null;
  }

  static KatalogLokal Bangun(IsiKatalogLokal isi) {
    final satuan = {for (final s in isi.satuan) s.Uuid: s};
    final satuanProduk = <String, List<SatuanJual>>{};
    for (final ps in isi.produkSatuan) {
      final s = satuan[ps.UuidSatuan];
      satuanProduk
          .putIfAbsent(ps.UuidProduk, () => [])
          .add(
            SatuanJual(
              uuid: ps.Uuid,
              nama: s?.Nama ?? '',
              konversiKeDasar: ps.KonversiKeDasar,
              defaultJual: ps.DefaultJual,
              bolehDesimal: s?.BolehDesimal ?? false,
            ),
          );
    }

    final pilihanKelompok = <String, List<PilihanJual>>{};
    for (final p in isi.pilihan.where((p) => p.Aktif)) {
      pilihanKelompok
          .putIfAbsent(p.UuidKelompokPilihan, () => [])
          .add(PilihanJual(uuid: p.Uuid, nama: p.Nama, harga: Uang.Dari(p.Harga)));
    }
    final kelompok = {
      for (final k in isi.kelompokPilihan)
        k.Uuid: KelompokPilihanJual(
          uuid: k.Uuid,
          nama: k.Nama,
          minimal: k.MinimalPilih,
          maksimal: k.MaksimalPilih,
          pilihan: pilihanKelompok[k.Uuid] ?? const [],
        ),
    };
    final kelompokProduk = <String, List<KelompokPilihanJual>>{};
    for (final t in isi.produkKelompokPilihan) {
      final k = kelompok[t.UuidKelompokPilihan];
      if (k != null && k.pilihan.isNotEmpty) {
        kelompokProduk.putIfAbsent(t.UuidProduk, () => []).add(k);
      }
    }

    final pajakKelompok = <String, List<PajakProduk>>{};
    for (final d in isi.kelompokPajakDetail) {
      pajakKelompok
          .putIfAbsent(d.UuidKelompokPajak, () => [])
          .add(PajakProduk(kode: d.KodeJenisPajak, dasarPengenaan: d.DasarPengenaan));
    }

    final produk = [
      for (final p in isi.produk)
        ProdukJual(
          uuid: p.Uuid,
          sku: p.Sku,
          nama: p.Nama,
          jenis: p.Jenis,
          uuidKategori: p.UuidKategori,
          pelacakan: p.Pelacakan,
          hargaTermasukPajak: p.HargaTermasukPajak,
          tampil: p.Aktif && p.TampilDiPos,
          satuan: satuanProduk[p.Uuid] ?? const [],
          kelompokPilihan: kelompokProduk[p.Uuid] ?? const [],
          pajak: p.UuidKelompokPajak == null ? const [] : pajakKelompok[p.UuidKelompokPajak] ?? const [],
        ),
    ];

    final hargaPerProduk = <String, List<BarisProdukHarga>>{};
    for (final h in isi.produkHarga) {
      hargaPerProduk
          .putIfAbsent(h.UuidProduk, () => [])
          .add(
            BarisProdukHarga(
              uuidProduk: h.UuidProduk,
              uuidProdukSatuan: h.UuidProdukSatuan,
              uuidDaftarHarga: h.UuidDaftarHarga,
              jumlahMinimum: Kuantitas.Dari(h.JumlahMinimum),
              harga: Uang.Dari(h.Harga),
            ),
          );
    }

    return KatalogLokal._(
      produk: produk,
      kategori: isi.kategori.where((k) => k.UuidInduk == null).toList(),
      petaProduk: {for (final p in produk) p.uuid: p},
      barcode: {
        for (final b in isi.produkBarcode) b.Barcode: (uuidProduk: b.UuidProduk, uuidProdukSatuan: b.UuidProdukSatuan),
      },
      daftarHarga: [for (final d in isi.daftarHarga) _KeResolusi(d)],
      hargaPerProduk: hargaPerProduk,
    );
  }

  static DaftarHargaResolusi _KeResolusi(BarisDaftarHarga d) {
    final outlet = d.UuidOutlet == null ? null : jsonDecode(d.UuidOutlet!);
    final kanal = KanalPenjualan.values.where((k) => k.name == d.Kanal).firstOrNull;
    return DaftarHargaResolusi(
      uuid: d.Uuid,
      // Kanal yang belum dikenal aplikasi versi ini tidak boleh dianggap "semua kanal".
      aktif: d.Aktif && (d.Kanal == null || kanal != null),
      uuidOutlet: outlet is List<Object?> ? outlet.whereType<String>().toList() : null,
      kanal: kanal,
      tierPelanggan: d.TierPelanggan,
      mulaiPada: d.MulaiPada?.toUtc(),
      selesaiPada: d.SelesaiPada?.toUtc(),
      prioritas: d.Prioritas,
    );
  }
}
