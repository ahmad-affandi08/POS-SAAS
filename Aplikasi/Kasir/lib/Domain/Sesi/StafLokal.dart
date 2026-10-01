import 'dart:convert';

import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';

/// Izin tenant yang dipakai aplikasi kasir (sama dengan `IzinTenant` server).
abstract final class IzinKasir {
  static const String penjualanBuat = 'penjualan.buat';
  static const String kasKeluarSetujui = 'kas.keluar.setujui';
  static const String penjualanDiskonManual = 'penjualan.diskon.manual';
  static const String penjualanDiskonSetujui = 'penjualan.diskon.setujui';
  static const String shiftSelisihSetujui = 'shift.selisih.setujui';

  /// F-09: melayani & menyetujui void/retur penjualan.
  static const String penjualanVoid = 'penjualan.void';

  /// F-12 BR-12.1: menyetujui penjualan tempo melebihi limit kredit / piutang lewat jatuh tempo.
  static const String penjualanTempoSetujui = 'penjualan.tempo.setujui';

  /// v2.00 mode Pelayan: mencatat pesanan meja & mengirim ke dapur tanpa berjualan/menerima pembayaran.
  static const String pesananMejaCatat = 'pesanan.meja.catat';

  /// F-07 mode service: mengelola reservasi (check-in pelanggan juga boleh dengan `penjualan.buat`).
  static const String reservasiKelola = 'reservasi.kelola';

  /// Laundry: mengubah status proses cucian (juga boleh dengan `penjualan.buat`).
  static const String laundryKelola = 'laundry.kelola';

  /// F-05f bagian 2: mencatat bahan/menu terbuang dari perangkat.
  static const String persediaanTerbuangCatat = 'persediaan.terbuang.catat';

  /// POS-25 modul Gudang: terima transfer masuk & hitung stok opname (juga terima barang dari PO).
  static const String persediaanKelola = 'persediaan.kelola';

  /// POS-25 modul Gudang: terima barang dari PO yang sudah disetujui.
  static const String pembelianKelola = 'pembelian.kelola';

  /// F-17 BR-17.2: menandai produk habis / tersedia lagi (juga boleh dengan `penjualan.buat` atau `pesanan.meja.catat`).
  static const String produkKelola = 'produk.kelola';
}

/// Staf dari data awal (tabel `Staf` lokal).
class StafLokal {
  const StafLokal({required this.uuid, required this.nama, required this.pemilik, required this.izin, this.pin});

  final String uuid;
  final String nama;
  final bool pemilik;
  final List<String> izin;
  final PinTerbungkus? pin;

  bool PunyaIzin(String kunci) => pemilik || izin.contains(kunci);

  /// Boleh membuka/menambah/memindah pesanan meja: kasir (`penjualan.buat`) atau pelayan (`pesanan.meja.catat`).
  bool CekBolehCatatPesanan() => PunyaIzin(IzinKasir.penjualanBuat) || PunyaIzin(IzinKasir.pesananMejaCatat);

  static StafLokal DariBaris(BarisStaf baris) {
    final izin = jsonDecode(baris.Izin);
    return StafLokal(
      uuid: baris.Uuid,
      nama: baris.Nama,
      pemilik: baris.Pemilik,
      izin: izin is List<Object?> ? izin.whereType<String>().toList() : const [],
      pin: baris.PinGaram == null || baris.PinNonce == null || baris.PinSandi == null
          ? null
          : PinTerbungkus(garam: baris.PinGaram!, nonce: baris.PinNonce!, sandi: baris.PinSandi!),
    );
  }
}
