import 'package:drift/drift.dart' show Value;
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriPersediaan.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';

/// Alasan bahan terbuang (sama dengan enum `AlasanBahanTerbuang` server).
enum AlasanBahanTerbuang {
  Kedaluwarsa('Kedaluwarsa / basi'),
  Rusak('Rusak'),
  SalahBuat('Salah buat / dikembalikan tamu'),
  TidakTerjual('Sisa tidak terjual'),
  Tumpah('Tumpah / jatuh'),
  Lainnya('Lainnya');

  const AlasanBahanTerbuang(this.label);

  final String label;

  static AlasanBahanTerbuang? Cari(String kode) => values.where((a) => a.name == kode).firstOrNull;
}

/// Pencatatan bahan/menu terbuang di aplikasi kasir/dapur (F-05f bagian 2, INV-10 F&B). Sepenuhnya offline: baris lokal
/// + item outbox `BahanTerbuang.Catat` `{UuidProduk, Jumlah (satuan dasar), Alasan, Catatan?, UuidPengguna,
/// DibuatPada}`. Server mengurangi stok lokasi Toko outlet (menu resep/paket diuraikan ke bahannya) dan menjurnal Susut
/// J-05.4; stok kurang atau izin yang berubah tetap dicatat + ditinjau di back-office.
///
/// Produk yang boleh dipilih: aktif, berstok atau beresep (Stok, BahanBaku, Produksi, Resep, Paket), tanpa pelacakan
/// batch/seri, dan bukan paket sesi. Jasa, tanpa stok, induk varian, dan konsinyasi ditolak server, jadi tidak
/// ditawarkan.
class LayananBahanTerbuang {
  LayananBahanTerbuang({required this.repositori, PembuatUlid? ulid, DateTime Function()? jam})
    : _jam = jam ?? DateTime.now,
      _ulid = ulid ?? PembuatUlid(jam: jam);

  static const String jenisOutbox = 'BahanTerbuang.Catat';

  /// Batas tampilan daftar produk hasil cari (katalog besar tetap ringan).
  static const int batasHasilCari = 50;

  static const int panjangCatatanMaksimal = 255;

  /// Batas digit bulat jumlah satuan dasar (kolom `DECIMAL(18,4)` server).
  static const int digitBulatMaksimal = 14;

  static const Set<String> jenisBoleh = {'Stok', 'BahanBaku', 'Produksi', 'Resep', 'Paket'};

  final RepositoriPersediaan repositori;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Produk ini bisa dicatat terbuang dari perangkat?
  static bool CekBolehDicatat(ProdukJual p) =>
      p.aktif &&
      jenisBoleh.contains(p.jenis) &&
      p.pelacakan == JenisProdukKasir.pelacakanTidak &&
      !p.paketSesi &&
      AmbilPilihanSatuan(p).isNotEmpty;

  /// Produk yang bisa dicatat, disaring [kata] (nama/SKU, atau barcode persis), urut nama, maksimal [batasHasilCari].
  static List<ProdukJual> CariProduk(KatalogLokal katalog, String kata) {
    final kunci = kata.trim().toLowerCase();
    final dariKode = kunci.isEmpty ? null : katalog.CariKode(kata)?.produk;
    final hasil = <ProdukJual>[
      if (dariKode != null && CekBolehDicatat(dariKode)) dariKode,
      for (final p in katalog.produk)
        if (p != dariKode &&
            CekBolehDicatat(p) &&
            (kunci.isEmpty || p.nama.toLowerCase().contains(kunci) || (p.sku?.toLowerCase().contains(kunci) ?? false)))
          p,
    ];
    return hasil.take(batasHasilCari).toList();
  }

  /// Satuan yang bisa dipilih: satuan produk, ditambah satuan dasar di depan bila belum ada satuan berkonversi 1
  /// (bahan baku sering hanya punya satuan dasar).
  static List<SatuanJual> AmbilPilihanSatuan(ProdukJual p) {
    final adaDasar = p.satuan.any((s) => Decimal.tryParse(s.konversiKeDasar) == Decimal.one);
    return [if (!adaDasar && p.satuanDasar != null) p.satuanDasar!, ...p.satuan];
  }

  /// Satuan bawaan untuk mencatat: satuan dasar (konversi 1) bila ada, selain itu satuan jual bawaan.
  static SatuanJual? AmbilSatuanBawaan(ProdukJual p) {
    final pilihan = AmbilPilihanSatuan(p);
    return pilihan.where((s) => Decimal.tryParse(s.konversiKeDasar) == Decimal.one).firstOrNull ??
        p.AmbilSatuanBawaan() ??
        pilihan.firstOrNull;
  }

  /// Ubah teks jumlah isian (koma/titik desimal) menjadi [Kuantitas] pada satuan [satuan]; galat berbahasa Indonesia
  /// bila kosong, bukan angka, ≤ 0, desimal pada satuan bulat, atau lebih dari 4 desimal.
  static Kuantitas BacaJumlah(String teks, SatuanJual satuan) {
    final rapi = teks.trim().replaceAll(' ', '').replaceAll(',', '.');
    if (rapi.isEmpty) {
      throw const GalatKasir('JumlahKosong', 'Isi jumlah yang terbuang.');
    }
    final nilai = RegExp(r'^\d+(\.\d+)?$').hasMatch(rapi) ? Decimal.tryParse(rapi) : null;
    if (nilai == null) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah harus berupa angka, misal 2 atau 0,5.');
    }
    if (nilai <= Decimal.zero) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah terbuang harus lebih dari 0.');
    }
    if (nilai.scale > Kuantitas.skala) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah maksimal 4 angka di belakang koma.');
    }
    if (!satuan.bolehDesimal && !nilai.isInteger) {
      throw GalatKasir('JumlahTidakValid', 'Satuan ${satuan.nama} harus bilangan bulat.');
    }
    return Kuantitas.DariDesimal(nilai);
  }

  /// Catat [jumlah] [produk] pada [satuan] yang terbuang karena [alasan] oleh [pencatat]. [tanggalBisnis] = tanggal
  /// bisnis outlet saat ini (`YYYY-MM-DD`). Hasil: Uuid catatan.
  Future<String> Catat({
    required ProdukJual produk,
    required SatuanJual satuan,
    required Kuantitas jumlah,
    required AlasanBahanTerbuang alasan,
    required StafLokal pencatat,
    required String tanggalBisnis,
    String? catatan,
  }) async {
    if (!pencatat.PunyaIzin(IzinKasir.persediaanTerbuangCatat)) {
      throw GalatKasir('TanpaIzin', '${pencatat.nama} tidak punya izin mencatat bahan terbuang.');
    }
    if (!CekBolehDicatat(produk)) {
      throw GalatKasir(
        'ProdukTidakBisaDicatat',
        '"${produk.nama}" tidak punya stok atau resep untuk dicatat terbuang.',
      );
    }
    if (!AmbilPilihanSatuan(produk).any((s) => s.uuid == satuan.uuid)) {
      throw GalatKasir('SatuanTidakValid', 'Satuan ${satuan.nama} bukan satuan ${produk.nama}.');
    }
    if (jumlah.BernilaiNol() || jumlah.BernilaiNegatif()) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah terbuang harus lebih dari 0.');
    }
    final konversi = Decimal.tryParse(satuan.konversiKeDasar);
    if (konversi == null || konversi <= Decimal.zero) {
      throw GalatKasir('SatuanTidakValid', 'Konversi satuan ${satuan.nama} tidak valid. Perbarui data katalog.');
    }
    final dasar = jumlah.Kali(konversi);
    if (dasar.BernilaiNol()) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah terlalu kecil untuk satuan ini.');
    }
    if (dasar.KeDesimal().toBigInt().toString().length > digitBulatMaksimal) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah terlalu besar. Periksa lagi angkanya.');
    }
    final teksCatatan = catatan?.trim();
    if (teksCatatan != null && teksCatatan.length > panjangCatatanMaksimal) {
      throw const GalatKasir('CatatanTerlaluPanjang', 'Catatan maksimal 255 karakter.');
    }

    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    final adaCatatan = teksCatatan != null && teksCatatan.isNotEmpty;
    await repositori.SimpanBahanTerbuang(
      BahanTerbuangLokalCompanion.insert(
        Uuid: uuid,
        UuidProduk: produk.uuid,
        NamaProduk: produk.nama,
        Jumlah: FormatJumlah(jumlah),
        NamaSatuan: satuan.nama,
        JumlahDasar: dasar.KeString(),
        Alasan: alasan.name,
        Catatan: Value(adaCatatan ? teksCatatan : null),
        UuidPengguna: pencatat.uuid,
        NamaPengguna: pencatat.nama,
        TanggalBisnis: tanggalBisnis,
        DibuatPada: sekarang,
      ),
      ItemOutbox(
        jenis: jenisOutbox,
        uuid: uuid,
        data: {
          'UuidProduk': produk.uuid,
          'Jumlah': dasar.KeString(),
          'Alasan': alasan.name,
          if (adaCatatan) 'Catatan': teksCatatan,
          'UuidPengguna': pencatat.uuid,
          'DibuatPada': sekarang.toIso8601String(),
        },
      ),
      sekarang,
    );
    return uuid;
  }

  /// Jumlah tanpa nol di belakang koma dengan koma desimal: `0.5000` → `0,5`, `2.0000` → `2`.
  static String FormatJumlah(Kuantitas jumlah) {
    final teks = jumlah.KeDesimal().toString();
    return teks.replaceAll('.', ',');
  }
}
