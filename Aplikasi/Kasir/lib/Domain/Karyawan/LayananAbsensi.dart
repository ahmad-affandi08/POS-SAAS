import 'dart:convert';
import 'dart:typed_data';

import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriAbsensi.dart';
import '../GalatKasir.dart';
import '../Perangkat/KameraSwafoto.dart';
import '../Sesi/StafLokal.dart';

/// Jenis absensi yang dicatat.
enum JenisAbsensi { Masuk, Keluar }

/// Hasil absen untuk layar konfirmasi.
class HasilAbsensi {
  const HasilAbsensi({required this.jenis, required this.nama, required this.waktu, this.masukPada});

  final JenisAbsensi jenis;
  final String nama;
  final DateTime waktu;

  /// Untuk absen keluar: waktu masuknya.
  final DateTime? masukPada;
}

/// Absensi di aplikasi kasir (Rincian F-18 bagian 1, EMP-03), berlaku offline:
/// - staf memilih namanya dan memasukkan PIN (diverifikasi lokal seperti masuk kasir), lalu swafoto kamera depan
///   bila perangkat berkamera (wajib; dibatalkan = tidak tercatat), perangkat tanpa kamera cukup PIN;
/// - belum ada absensi terbuka → `Absensi.Masuk` (Uuid item = Uuid absensi); ada → `Absensi.Keluar`;
/// - baris lokal + outbox satu transaksi SQLite; swafoto JPEG base64 hanya di outbox (≤ [ukuranMaksimalSwafoto]).
class LayananAbsensi {
  LayananAbsensi({required this.repositori, required this.kamera, PembuatUlid? ulid, DateTime Function()? jam})
    : _ulid = ulid ?? PembuatUlid(),
      _jam = jam ?? DateTime.now;

  static const String jenisMasuk = 'Absensi.Masuk';
  static const String jenisKeluar = 'Absensi.Keluar';

  /// Sama dengan server `config('karyawan.UkuranMaksimalSwafotoKb')`.
  static const int ukuranMaksimalSwafoto = 300 * 1024;

  final RepositoriAbsensi repositori;
  final KameraSwafoto kamera;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Status staf saat ini: null = belum masuk.
  Future<BarisAbsensiLokal?> AmbilTerbuka(StafLokal staf) => repositori.AmbilTerbuka(staf.uuid);

  /// Ambil swafoto bila kamera tersedia. Null = tanpa kamera. Dibatalkan = `GalatKasir` `SwafotoDibatalkan`.
  Future<Uint8List?> AmbilSwafoto() async {
    if (!kamera.CekTersedia()) {
      return null;
    }
    final foto = await kamera.Ambil();
    if (foto == null) {
      throw const GalatKasir('SwafotoDibatalkan', 'Swafoto wajib untuk absen. Coba lagi dan ambil foto wajah.');
    }
    return foto;
  }

  /// Catat absen masuk/keluar [staf] (PIN sudah diverifikasi pemanggil).
  Future<HasilAbsensi> Catat(StafLokal staf, Uint8List? swafoto) async {
    if (swafoto != null && swafoto.length > ukuranMaksimalSwafoto) {
      throw const GalatKasir('SwafotoTerlaluBesar', 'Swafoto terlalu besar. Ambil ulang foto.');
    }
    final sekarang = _jam().toUtc();
    final foto = swafoto == null ? null : base64Encode(swafoto);
    final terbuka = await repositori.AmbilTerbuka(staf.uuid);

    if (terbuka == null) {
      final uuid = _ulid.Buat();
      await repositori.SimpanMasuk(
        BarisAbsensiLokal(Uuid: uuid, UuidPengguna: staf.uuid, NamaStaf: staf.nama, MasukPada: sekarang),
        ItemOutbox(
          jenis: jenisMasuk,
          uuid: uuid,
          data: {'UuidPengguna': staf.uuid, 'MasukPada': sekarang.toIso8601String(), 'Swafoto': foto},
        ),
        sekarang,
      );
      return HasilAbsensi(jenis: JenisAbsensi.Masuk, nama: staf.nama, waktu: sekarang);
    }

    await repositori.SimpanKeluar(
      terbuka.Uuid,
      sekarang,
      ItemOutbox(
        jenis: jenisKeluar,
        uuid: _ulid.Buat(),
        data: {
          'UuidAbsensi': terbuka.Uuid,
          'UuidPengguna': staf.uuid,
          'KeluarPada': sekarang.toIso8601String(),
          'Swafoto': foto,
        },
      ),
      sekarang,
    );
    return HasilAbsensi(jenis: JenisAbsensi.Keluar, nama: staf.nama, waktu: sekarang, masukPada: terbuka.MasukPada);
  }
}
